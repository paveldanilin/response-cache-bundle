<?php

namespace Pada\ResponseCacheBundle\Tests\EventListener;

use Pada\ResponseCacheBundle\Tests\BundleTestCase;
use Pada\ResponseCacheBundle\Tests\Fixtures\TestController;

class ControllerCacheListenerTest extends BundleTestCase
{
    protected function setUp(): void
    {
        $this->init();
    }

    public function testRequest(): void
    {
        $this->invokeMethod('/api/v1/get/array?a=1&b=1', 'GET', 'getArray');

        self::assertCount(1, $this->cacheApp->getValues());
    }

    public function testUnknownPool(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('A cache pool not found [unknown.pool]');

        $this->invokeMethod('/api/v1/users/1', 'GET', 'getUser');
    }

    public function testStaticKey(): void
    {
        $this->invokeMethod('/api/v1/data/static/key', 'GET', 'getDataStaticKey');

        self::assertArrayHasKey(\md5('static_key_data'), $this->cacheApp->getValues());
    }

    public function testDynamicKeyRequest(): void
    {
        $this->invokeMethod(
            '/api/v1/data/static/key',
            'GET',
            'getDataDynamicKey'
        );

        self::assertArrayHasKey(\md5('/api/v1/data/static/key'), $this->cacheApp->getValues());
    }

    public function testDynamicKeyQuery(): void
    {
        $this->invokeMethod(
            '/api/v1/data/static/key?first_name=Pavel&last_name=Danilin',
            'GET',
            'getDynamicKeyQuery'
        );

        self::assertArrayHasKey(\md5('PavelDanilin'), $this->cacheApp->getValues());
    }

    public function testSkipCacheByCondition(): void
    {
        $this->invokeMethod(
            '/api/v1/data/static/key?a=1',
            'PUT',
            'cacheIfMethodNotPut'
        );

        self::assertArrayNotHasKey(\md5('cached_data_with_condition'), $this->cacheApp->getValues());
    }

    public function testCacheByCondition(): void
    {
        $this->invokeMethod('/api/v1/data/static/key?a=1', 'POST', 'cacheIfMethodNotPut');

        self::assertArrayHasKey(\md5('cached_data_with_condition'), $this->cacheApp->getValues());
    }

    private function invokeMethod(string $uri, string $httpMethod, string $controllerMethod): void
    {
        $request = $this->createRequest($uri, $httpMethod);

        $controllerEvent = $this->createControllerEvent(new TestController(), $controllerMethod, $request);
        $this->controllerCacheListener->onKernelController($controllerEvent);

        $handler = $controllerEvent->getController();
        $response = $handler($request);

        $this->responseCacheListener->onKernelResponse($this->createResponseEvent($request, $response));
    }
}
