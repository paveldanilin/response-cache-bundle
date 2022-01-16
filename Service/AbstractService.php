<?php

namespace Pada\ResponseCacheBundle\Service;

use Pada\Reflection\Scanner\ClassInfo;
use Pada\Reflection\Scanner\ScannerInterface;
use Pada\ResponseCacheBundle\Controller\Annotation\AbstractAnnotation;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpKernel\Exception\HttpException;

abstract class AbstractService
{
    private const CACHE_PREFIX = 'pada_rc_';

    private LoggerInterface $logger;
    private ScannerInterface $metaScanner;
    private CacheItemPoolInterface $cacheSystem;
    private ContainerInterface $locator;

    public function __construct(ScannerInterface       $metaScanner,
                                CacheItemPoolInterface $cacheSystem,
                                ContainerInterface     $locator)
    {
        $this->logger = new NullLogger();
        $this->metaScanner = $metaScanner;
        $this->cacheSystem = $cacheSystem;
        $this->locator = $locator;
    }

    abstract protected function doWarmUpSystemCache(ClassInfo $classInfo, string $methodName): void;

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    protected function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    public function warmUpSystemCache(string $scanDir): void
    {
        /** @var ClassInfo $classInfo */
        foreach ($this->metaScanner->in($scanDir) as $classInfo) {
            foreach ($classInfo->getMethodNames() as $methodName) {
                $this->doWarmUpSystemCache($classInfo, $methodName);
            }
        }
    }

    /**
     * @param string $controllerClassName
     * @param string $method
     * @param string $annotationClass
     * @return mixed|null
     */
    protected function systemMetaCacheGet(string $controllerClassName, string $method, string $annotationClass)
    {
        $key = self::getCacheKey($controllerClassName, $method, $annotationClass);
        $cachedItem = $this->cacheSystem->getItem($key);
        if ($cachedItem->isHit()) {
            return $cachedItem->get();
        }
        return null;
    }

    protected function systemMetaCachePut(ClassInfo $classInfo, string $methodName, AbstractAnnotation $annotation): void
    {
        if (empty($annotation->key)) {
            $annotation->key = $classInfo->getReflection()->getName() . '_' . $methodName;
        }
        $key = self::getCacheKey($classInfo->getReflection()->getName(), $methodName, \get_class($annotation));
        $cachedItem = $this->cacheSystem->getItem($key);
        $cachedItem->set($annotation);
        $this->cacheSystem->save($cachedItem);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function findPool(string $poolId): CacheItemPoolInterface
    {
        $pool = $this->locator->get($poolId);
        if (null === $pool) {
            throw new \RuntimeException(\sprintf('A cache pool not found [%s]', $poolId));
        }
        if ($pool instanceof CacheItemPoolInterface) {
            return $pool;
        }
        throw new \RuntimeException(\sprintf('A cache pool [%s] must implement \Psr\Cache\CacheItemPoolInterface', $poolId));
    }

    /**
     * @param \Exception $exception
     * @param mixed $controller
     * @param string $method
     * @param string $annotationClass
     */
    protected static function throwServerException(\Exception $exception, $controller, string $method, string $annotationClass): void
    {
        throw new HttpException(
            500,
            \sprintf(
                'Failed to process annotation @%s at %s->%s(%s). %s',
                $annotationClass,
                \get_class($controller),
                $method,
                self::stringifyMethodArguments($controller, $method),
                $exception->getMessage()
            ),
            $exception
        );
    }

    protected static function getClassName(string $class): string
    {
        $parts = \explode('\\', $class);
        return \end($parts);
    }

    /**
     * @param mixed $controller
     * @param string $method
     * @return string
     */
    private static function stringifyMethodArguments($controller, string $method): string
    {
        try {
            $reflection = new \ReflectionMethod($controller, $method);

            return \implode(',', \array_map(static function (\ReflectionParameter $parameter) {
                $type = $parameter->getType();
                if ($type instanceof \ReflectionNamedType) {
                    $type = $type->getName();
                }
                return \sprintf('<%s>%s', $type ?? '', $parameter->getName());
            }, $reflection->getParameters()));

        } catch (\ReflectionException $exception) {
            return '';
        }
    }

    public static function getCacheKey(string $controllerClass, string $method, string $annotationClass): string
    {
        return self::CACHE_PREFIX . \md5($controllerClass . '_' . $method . '_' . $annotationClass);
    }
}
