<?php

namespace Pada\ResponseCacheBundle\Tests;

use Pada\Reflection\Scanner\Scanner;
use Pada\ResponseCacheBundle\Cache\CacheWarmer;
use Pada\ResponseCacheBundle\Controller\Annotation\AbstractAnnotation;
use Pada\ResponseCacheBundle\EventListener\ControllerListener;
use Pada\ResponseCacheBundle\EventListener\ResponseListener;
use Pada\ResponseCacheBundle\Service\CacheableService;
use Pada\ResponseCacheBundle\Service\CacheableServiceInterface;
use Pada\ResponseCacheBundle\Service\ExpressionService;
use Pada\ResponseCacheBundle\Service\KeyHashGenerator;
use Pada\ResponseCacheBundle\Service\LockStoreFactory;
use PHPStan\Testing\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class BundleTestCase extends TestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject|HttpKernelInterface */
    protected $kernel;

    protected ArrayAdapter $cacheSystem;
    protected ArrayAdapter $cacheApp;
    protected CacheableServiceInterface $service;
    protected ControllerListener $controllerCacheListener;
    protected ResponseListener $responseCacheListener;

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
        $this->service = new CacheableService(
            new Scanner(),
            $container,
            $this->cacheSystem,
            $expressionService,
            new KeyHashGenerator($expressionService),
            new LockStoreFactory(),
        );
        $this->service->setLogger($logger);

        $dir = getcwd() . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'Fixtures';
        $warmer = new CacheWarmer($dir, $this->service);
        $warmer->setLogger($logger);
        $warmer->warmUp('');

        $this->controllerCacheListener = new ControllerListener($this->service);
        $this->responseCacheListener = new ResponseListener($this->service);
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
