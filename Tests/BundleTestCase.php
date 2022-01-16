<?php

namespace Pada\ResponseCacheBundle\Tests;

use Pada\Reflection\Scanner\Scanner;
use Pada\ResponseCacheBundle\Cache\CacheWarmer;
use Pada\ResponseCacheBundle\Controller\Annotation\AbstractAnnotation;
use Pada\ResponseCacheBundle\EventListener\ResponseCacheControllerListener;
use Pada\ResponseCacheBundle\EventListener\ResponseCacheResponseListener;
use Pada\ResponseCacheBundle\Service\CacheableService;
use Pada\ResponseCacheBundle\Service\CacheableServiceInterface;
use Pada\ResponseCacheBundle\Service\EvictService;
use Pada\ResponseCacheBundle\Service\EvictServiceInterface;
use Pada\ResponseCacheBundle\Service\ExpressionService;
use Pada\ResponseCacheBundle\Service\KeyGenerator;
use PHPStan\Testing\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\SemaphoreStore;

class BundleTestCase extends TestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject|HttpKernelInterface */
    protected $kernel;

    protected ArrayAdapter $cacheSystem;
    protected ArrayAdapter $cacheApp;
    protected CacheableServiceInterface $cacheableService;
    protected EvictServiceInterface $evictService;
    protected ResponseCacheControllerListener $controllerCacheListener;
    protected ResponseCacheResponseListener $responseCacheListener;

    protected function init(): void
    {
        $this->kernel = $this->getMockBuilder(HttpKernelInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->cacheSystem = new ArrayAdapter();
        $this->cacheApp = new ArrayAdapter();
        $container = new TestContainer([
            AbstractAnnotation::DEFAULT_APP_CACHE_POOL => $this->cacheApp,
        ]);
        $logger = new ConsoleLogger(new ConsoleOutput());
        $expressionService = new ExpressionService($this->cacheSystem);
        $metaScanner = new Scanner();

        $this->cacheableService = new CacheableService(
            $metaScanner,
            $container,
            $this->cacheSystem,
            $expressionService,
            new KeyGenerator($expressionService),
            new LockFactory(new SemaphoreStore()),
        );
        $this->cacheableService->setLogger($logger);

        $this->evictService = new EvictService(
            $metaScanner,
            $container,
            $this->cacheSystem,
            new KeyGenerator($expressionService)
        );
        $this->evictService->setLogger($logger);

        $dir = getcwd() . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'Fixtures';
        $warmer = new CacheWarmer($dir, $this->cacheableService, $this->evictService);
        $warmer->setLogger($logger);
        $warmer->warmUp('');

        $this->controllerCacheListener = new ResponseCacheControllerListener($this->cacheableService, $this->evictService);
        $this->responseCacheListener = new ResponseCacheResponseListener($this->cacheableService);
    }

    protected function createRequest(string $uri, string $method = 'GET', array $params = [], ?string $content = null, array $headers = []): Request
    {
        $request = Request::create($uri, $method, $params, [], [], [], $content);
        foreach ($headers as $key => $header) {
            $request->headers->set($key, $header);
        }
        return $request;
    }

    /**
     * @param mixed $controller
     * @param string $method
     * @param Request $request
     * @return ControllerEvent
     */
    protected function createControllerEvent($controller, string $method, Request $request): ControllerEvent
    {
        return new ControllerEvent(
            $this->kernel,
            [$controller, $method],
            $request,
            HttpKernelInterface::MASTER_REQUEST
        );
    }

    protected function createResponseEvent(Request $request, Response $response): ResponseEvent
    {
        return new ResponseEvent($this->kernel, $request, HttpKernelInterface::MASTER_REQUEST, $response);
    }
}
