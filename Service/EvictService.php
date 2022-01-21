<?php

namespace Pada\ResponseCacheBundle\Service;

use Pada\Reflection\Scanner\ClassInfo;
use Pada\Reflection\Scanner\ScannerInterface;
use Pada\ResponseCacheBundle\Controller\Annotation\CacheEvict;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

final class EvictService extends AbstractService implements EvictServiceInterface
{
    private KeyGeneratorInterface $keyGenerator;

    public function __construct(ScannerInterface       $metaScanner,
                                ContainerInterface     $container,
                                CacheItemPoolInterface $cacheSystem,
                                KeyGeneratorInterface  $keyGenerator)
    {
        parent::__construct($metaScanner, $cacheSystem, $container);
        $this->keyGenerator = $keyGenerator;
    }

    public function processEvent($controller, string $method, ControllerEvent $event): void
    {
        $controllerClassName = \get_class($controller);
        try {
            $annotation = $this->getAnnotationFromSystemCache($controllerClassName, $method);
            if (null !== $annotation) {

                if ($annotation->skipKeyGen) {
                    $keyHash = $annotation->key;
                } else {
                    $keyHash = $this->keyGenerator->generate($annotation->key, $event->getRequest());
                }

                $pool = $this->findPool($annotation->pool);

                if ($pool->deleteItem($keyHash)) {
                    $this->getLogger()
                        ->debug('A value has been evicted. pool={pool}; key={key};', [
                            'key' => $keyHash,
                            'pool' => $annotation->pool,
                        ]);
                } else {
                    $this->getLogger()->debug('Could not evict unknown key. pool={pool}; key={key};', [
                        'key' => $keyHash,
                        'pool' => $annotation->pool
                    ]);
                }
            }
        } catch (\Exception $e) {
            $this->getLogger()->error($e->getMessage());
            self::throwServerException($e, $controller, $method, self::getClassName(CacheEvict::class));
        }
    }

    protected function doWarmUpSystemCache(ClassInfo $classInfo, string $methodName): void
    {
        $annotation = $this->getFirstMethodAnnotation($classInfo, $methodName);
        if (null === $annotation) {
            return;
        }
        $this->systemMetaCachePut($classInfo, $methodName, $annotation);
    }

    private function getFirstMethodAnnotation(ClassInfo $classInfo, string $method): ?CacheEvict
    {
        foreach ($classInfo->getMethodAnnotations($method) as $annotation) {
            if ($annotation instanceof CacheEvict) {
                return $annotation;
            }
        }
        return null;
    }

    private function getAnnotationFromSystemCache(string $controllerClassName, string $method): ?CacheEvict
    {
        return $this->systemMetaCacheGet($controllerClassName, $method, CacheEvict::class);
    }
}
