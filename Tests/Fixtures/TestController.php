<?php

namespace Pada\ResponseCacheBundle\Tests\Fixtures;

use Pada\ResponseCacheBundle\Controller\Annotation\Cacheable;
use Pada\ResponseCacheBundle\Controller\Annotation\CacheEvict;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class TestController
{
    /**
     * @Cacheable()
     * @return Response
     */
    public function getArray(): Response
    {
        return new Response('[1,2,3]', 200, ['Content-Type' => 'application/json']);
    }

    /**
     * @Cacheable(pool="unknown.pool")
     * @return Response
     */
    public function getUser(): Response
    {
        return new Response('{}', 200);
    }

    /**
     * @Cacheable(key="static_key_data")
     * @return Response
     */
    public function getDataStaticKey(): Response
    {
        return new Response('DATA', 200);
    }

    /**
     * @see https://symfony.com/doc/current/components/http_foundation.html#request
     * @Cacheable(key="#request.getRequestUri()")
     * @param Request $request
     * @return Response
     */
    public function getDataDynamicKey(Request $request): Response
    {
        return new Response('DATA', 200);
    }

    /**
     * @Cacheable(key="#query_val('first_name')~query_val('last_name')")
     * @param Request $request
     * @return Response
     */
    public function getDynamicKeyQuery(Request $request): Response
    {
        return new Response('DATA', 200);
    }

    /**
     * @Cacheable(key="#query_val(['first_name', 'last_name'])")
     * @param Request $request
     * @return Response
     */
    public function getDynamicKeyQueryWithFunc(Request $request): Response
    {
        return new Response('DATA', 301);
    }

    /**
     * @Cacheable(key="cached_data_with_condition", condition="request.getMethod()!='PUT'")
     * @return Response
     */
    public function cacheIfMethodNotPut(): Response
    {
        return new Response('DATA', 200);
    }

    /**
     * @Cacheable(key="ABCD")
     * @return Response
     */
    public function createABCDKey(): Response
    {
        return new Response('DATA', 201);
    }

    /**
     * @CacheEvict(key="ABCD")
     * @return Response
     */
    public function evictABCDKey(): Response
    {
        return new Response('DATA', 200);
    }

    /**
     * @Cacheable(key="123321", skipKeyGen=true)
     * @return Response
     */
    public function createKeyWithoutKeyGeneration(): Response
    {
        return new Response('DATA', 200);
    }
}
