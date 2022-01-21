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
use Symfony\Component\Lock\Store\FlockStore;


final class CacheableService extends AbstractService implements CacheableServiceInterface
{
    private LockFactory $lockFactory;
    private ExpressionServiceInterface $expressionService;
    private KeyGeneratorInterface $keyGenerator;

    public function __construct(ScannerInterface           $metaScanner,
                                ContainerInterface         $container,
                                CacheItemPoolInterface     $cacheSystem,
                                ExpressionServiceInterface $expressionService,
                                KeyGeneratorInterface      $keyGenerator,
                                ?LockFactory               $lockFactory)
    {
        parent::__construct($metaScanner, $cacheSystem, $container);
        $this->expressionService = $expressionService;
        $this->keyGenerator = $keyGenerator;
        $this->lockFactory = $lockFactory ?? new LockFactory(new FlockStore());
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
        if (null === $cacheable || !$this->requestMatchesCondition($cacheable->condition, $event->getRequest())) {
            $this->getLogger()
                ->debug('A method is not @Cacheable or does not match to condition, skip processing.');
            return;
        }

        $this->lockFactory->setLogger($this->getLogger());

        if ($cacheable->skipKeyGen) {
            $keyHash = $cacheable->key;
        } else {
            $keyHash = $this->keyGenerator->generate($cacheable->key, $event->getRequest());
        }

        $startCacheOperations = \microtime(true); // @see createResponseFromCacheItem
        $pool = $this->findPool($cacheable->pool);
        $cacheItem = $pool->getItem($keyHash);

        if ($cacheItem->isHit()) {
            // Replace controller handler
            $event->setController(fn() => $this->createResponseFromCacheItem($cacheItem, $cacheable, $startCacheOperations));
            $this->getLogger()
                ->debug('A response has been created from the cache. pool={pool}; key={key};', [
                    'key' => $keyHash,
                    'pool' => $cacheable->pool,
                ]);
            return;
        }

        $this->createLock($event->getRequest(), $keyHash, $pool, $cacheItem, $cacheable);
    }

    public function updateCacheIfNeeded(ResponseEvent $event): void
    {
        if (!$this->shouldUpdateCache($event)) {
            $this->getLogger()->debug('A cacheable value should not be updated.');
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
            'headers'       => $event->getResponse()->headers->all(),
            'status_code'   => $event->getResponse()->getStatusCode(),
            'content'       => $event->getResponse()->getContent()
        ]);

        if ($pool->save($cachedItem)) {
            $this->getLogger()->debug(
                'A value has been saved to cache. pool={pool}; key={key};', [
                    'key' => $cachedItem->getKey(),
                    'pool' => $cacheable->pool,
                ]);
        } else {
            $this->getLogger()->error(
                'Could not persist value to cache. pool={pool}; key={key};', [
                    'key' => $cachedItem->getKey(),
                    'pool' => $cacheable->pool,
                ]);
        }

        $this->releaseLockIfNeeded($event->getRequest());
    }

    public function releaseLockIfNeeded(Request $request): void
    {
        if ($request->attributes->has(Cacheable::REQUEST_LOCK)) {
            /** @var CacheItemInterface $cachedItem */
            $cachedItem = $request->attributes->get(Cacheable::REQUEST_CACHED_ITEM);

            /** @var Cacheable $cacheable */
            $cacheable = $request->attributes->get(Cacheable::REQUEST_ATTRIBUTE);

            /** @var LockInterface $lock */
            $lock = $request->attributes->get(Cacheable::REQUEST_LOCK);
            $lock->release();
            $request->attributes->remove(Cacheable::REQUEST_LOCK);

            $this->getLogger()->debug('The lock has been released. pool={pool}; key={key};', [
                'key' => $cachedItem->getKey(),
                'pool' => $cacheable->pool,
            ]);
        }
    }

    private function requestMatchesCondition(?string $condition, Request $request): bool
    {
        if (null === $condition) {
            return true;
        }
        return (bool)$this->expressionService->evaluateOnRequest($condition, $request);
    }

    private function shouldUpdateCache(ResponseEvent $event): bool
    {
        return $event->getRequest()->attributes->has(Cacheable::REQUEST_ATTRIBUTE) &&
            $event->getResponse()->isSuccessful() &&
            !$event->getResponse()->isEmpty();
    }

    private function createLock(Request $request, string $keyHash, CacheItemPoolInterface $pool, CacheItemInterface $cacheItem, Cacheable $cacheable): void
    {
        $lock = $this->lockFactory->createLock($keyHash, 30.0, false);
        if ($lock->acquire(false)) {
            $this->getLogger()
                ->debug('A lock has been created for a cache computation. pool={pool}; key={key};', [
                    'key' => $keyHash,
                    'pool' => $cacheable->pool,
                ]);
            $request->attributes->set(Cacheable::REQUEST_LOCK, $lock);
            $request->attributes->set(Cacheable::REQUEST_POOL_SERVICE, $pool);
            $request->attributes->set(Cacheable::REQUEST_CACHED_ITEM, $cacheItem);
            $request->attributes->set(Cacheable::REQUEST_ATTRIBUTE, $cacheable);
        } else {
            $this->getLogger()
                ->debug('Could not create a lock for a cache computation. pool={pool}; key={key};', [
                    'key' => $keyHash,
                    'pool' => $cacheable->pool,
                ]);
        }
    }

    private function createResponseFromCacheItem(CacheItemInterface $cacheItem, Cacheable $cacheable, float $startCacheOperations): Response
    {
        $cachedResponse = $cacheItem->get();
        $cacheOperationsTime = \microtime(true) - $startCacheOperations;
        $this->getLogger()
            ->debug('Cache reading took {took} seconds. pool={pool}; key={key};', [
                'took' => $cacheOperationsTime,
                'key' => $cacheItem->getKey(),
                'pool' => $cacheable->pool,
            ]);
        return new Response(
            $cachedResponse['content'] ?? '',
            $cachedResponse['status_code'] ?? 200,
            $cachedResponse['headers'] ?? [],
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

        if ($this->keyGenerator->isKeyDynamic($cacheable->key)) {
            $this->expressionService->evaluateOnRequest($this->keyGenerator->normalize($cacheable->key), new Request());
        }

        if (!empty($cacheable->condition)) {
            $this->expressionService->evaluateOnRequest($cacheable->condition, new Request());
        }

        $this->systemMetaCachePut($classInfo, $methodName, $cacheable);
    }

    private function getCacheableFromSystemCache(string $controllerClassName, string $method): ?Cacheable
    {
        return $this->systemMetaCacheGet($controllerClassName, $method, Cacheable::class);
    }
}
