<?php

namespace Pada\ResponseCacheBundle\EventListener;

use Pada\ResponseCacheBundle\Service\CacheableServiceInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

class ResponseCacheExceptionListener
{
    private CacheableServiceInterface $cacheableService;

    public function __construct(CacheableServiceInterface $service)
    {
        $this->cacheableService = $service;
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $this->cacheableService->releaseLockIfNeeded($event->getRequest());
    }
}
