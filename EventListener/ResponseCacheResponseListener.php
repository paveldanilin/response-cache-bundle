<?php


namespace Pada\ResponseCacheBundle\EventListener;

use Pada\ResponseCacheBundle\Service\CacheableServiceInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class ResponseCacheResponseListener
{
    private CacheableServiceInterface $cacheableService;

    public function __construct(CacheableServiceInterface $service)
    {
        $this->cacheableService = $service;
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $this->cacheableService->updateCacheIfNeeded($event);
    }
}
