<?php

namespace Pada\ResponseCacheBundle\Service;

use Pada\Reflection\Scanner\ClassInfo;
use Pada\Reflection\Scanner\ScannerInterface;
use Pada\ResponseCacheBundle\Controller\Annotation\Cacheable;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;


final class CacheableService extends AbstractService implements CacheableServiceInterface
{
    private LockFactory $lockFactory;
    private ExpressionRequestAwareInterface $expressionRequestAware;
    private KeyHashGeneratorInterface $keyHashGenerator;

    public function __construct(ScannerInterface $metaScanner,
                                ContainerInterface $container,
                                CacheItemPoolInterface $cacheSystem,
                                ExpressionRequestAwareInterface $expressionRequestAware,
                                KeyHashGeneratorInterface $keyHashGenerator,
                                LockStoreFactoryInterface $lockStoreFactory,
                                string $lockStoreDsn)
    {
        parent::__construct($metaScanner, $cacheSystem, $container);
        $this->expressionRequestAware = $expressionRequestAware;
        $this->keyHashGenerator = $keyHashGenerator;
        $this->lockFactory = new LockFactory($lockStoreFactory->create($lockStoreDsn ));
    }

    public function processEvent($controller, string $method, ControllerEvent $event): void
    {
        $controllerClassName = \get_class($controller);
        try {
            $this->doProcessEvent($this->getCacheableFromSystemCache($controllerClassName, $method), $event);
        } catch (\Exception $e) {
            $this->getLogger()->error($e->getMessage());
            $this->releaseLockIfNeeded($event->getRequest());
            self::throwServerException($e, $controller, $method, self::getClassName(Cacheable::class));
        }
    }

    private function doProcessEvent(?Cacheable $cacheable, ControllerEvent $event): void
    {
        // Skip processing if whether a cacheable is null or condition does not match
        if (null === $cacheable || !$this->matchCondition($cacheable->condition, $event->getRequest())) {
            $this->getLogger()->debug('A method is not @Cacheable, skip processing.');
            return;
        }

        $keyHash = $this->keyHashGenerator->generate($cacheable->key, $event->getRequest());

        $pool = $this->findPool($cacheable->pool);
        $cacheItem = $pool->getItem($keyHash);

        if ($cacheItem->isHit()) {
            // Replace controller handler
            $event->setController(fn() => $this->createResponseFromCacheItem($cacheItem));
            $this->getLogger()->debug('A response has been created from cache.');
            return;
        }

        $this->createLock($event->getRequest(), $keyHash, $pool, $cacheItem, $cacheable);
    }

    public function updateCacheIfNeeded(ResponseEvent $event): void
    {
        if (!$this->shouldUpdateCache($event)) {
            $this->getLogger()->debug('A cacheable value should not been updated');
            $this->releaseLockIfNeeded($event->getRequest());
            return;
        }

        /** @var Cacheable $cacheable */
        $cacheable = $event->getRequest()->attributes->get(Cacheable::REQUEST_ATTRIBUTE);

        /** @var CacheItemPoolInterface $pool */
        $pool  = $event->getRequest()->attributes->get(Cacheable::REQUEST_POOL_SERVICE);

        /** @var CacheItemInterface $cachedItem */
        $cachedItem = $event->getRequest()->attributes->get(Cacheable::REQUEST_CACHED_ITEM);

        if (null !== $cacheable->ttl && $cacheable->ttl > 0) {
            $cachedItem->expiresAfter($cacheable->ttl);
        }

        $cachedItem->set([
            'headers' => $event->getResponse()->headers->all(),
            'status_code' => $event->getResponse()->getStatusCode(),
            'content' => $event->getResponse()->getContent()
        ]);

        $pool->save($cachedItem);

        $this->releaseLockIfNeeded($event->getRequest());
    }

    public function releaseLockIfNeeded(Request $request): void
    {
        if ($request->attributes->has(Cacheable::REQUEST_LOCK)) {
            /** @var LockInterface $lock */
            $lock = $request->attributes->get(Cacheable::REQUEST_LOCK);
            $lock->release();
            $request->attributes->remove(Cacheable::REQUEST_LOCK);
        }
    }

    private function matchCondition(?string $condition, Request $request): bool
    {
        if (null === $condition) {
            return true;
        }
        return (bool)$this->expressionRequestAware->evaluateOnRequest($condition, $request);
    }

    private function shouldUpdateCache(ResponseEvent $event): bool
    {
        return $event->getRequest()->attributes->has(Cacheable::REQUEST_ATTRIBUTE) &&
            $event->getResponse()->isSuccessful() &&
            !$event->getResponse()->isEmpty();
    }

    private function createLock(Request $request, string $keyHash, CacheItemPoolInterface $pool, CacheItemInterface $cacheItem, Cacheable $cacheable): void
    {
        $lock = $this->lockFactory->createLock($keyHash, 30, false);
        if ($lock->acquire(false)) {
            $this->getLogger()->debug('A lock has been created for a cache value computation.');
            $request->attributes->set(Cacheable::REQUEST_LOCK, $lock);
            $request->attributes->set(Cacheable::REQUEST_POOL_SERVICE, $pool);
            $request->attributes->set(Cacheable::REQUEST_CACHED_ITEM, $cacheItem);
            $request->attributes->set(Cacheable::REQUEST_ATTRIBUTE, $cacheable);
        } else {
            $this->getLogger()->debug('Could not create a lock for a cache computation.');
        }
    }

    private function createResponseFromCacheItem(CacheItemInterface $cacheItem): Response
    {
        $cachedResponse = $cacheItem->get();
        return new Response(
            $cachedResponse['content'],
            $cachedResponse['status_code'],
            $cachedResponse['headers']
        );
    }

    private function getFirstMethodAnnotation(ClassInfo $classInfo, string $method): ?Cacheable
    {
        foreach ($classInfo->getMethodAnnotations($method) as $annotation) {
            if ($annotation instanceof Cacheable) {
                return $annotation;
            }
        }
        return null;
    }

    protected function doWarmUpSystemCache(ClassInfo $classInfo, string $methodName): void
    {
        $cacheable = $this->getFirstMethodAnnotation($classInfo, $methodName);
        if (null === $cacheable) {
            return;
        }

        if ($this->keyHashGenerator->isKeyDynamic($cacheable->key)) {
            $this->expressionRequestAware->evaluateOnRequest($this->keyHashGenerator->normalize($cacheable->key), new Request());
        }

        if (!empty($cacheable->condition)) {
            $this->expressionRequestAware->evaluateOnRequest($cacheable->condition, new Request());
        }

        $this->systemMetaCachePut($classInfo, $methodName, $cacheable);
    }

    private function getCacheableFromSystemCache(string $controllerClassName, string $method): ?Cacheable
    {
        return $this->systemMetaCacheGet($controllerClassName, $method, Cacheable::class);
    }
}
