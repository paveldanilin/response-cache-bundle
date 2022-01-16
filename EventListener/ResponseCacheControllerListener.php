<?php


namespace Pada\ResponseCacheBundle\EventListener;

use Pada\ResponseCacheBundle\Service\CacheableServiceInterface;
use Pada\ResponseCacheBundle\Service\EvictServiceInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;


class ResponseCacheControllerListener
{
    private CacheableServiceInterface $cacheableService;
    private EvictServiceInterface $evictService;

    public function __construct(CacheableServiceInterface $cacheableService, EvictServiceInterface $evictService)
    {
        $this->cacheableService = $cacheableService;
        $this->evictService = $evictService;
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $controllerMeta = $event->getController();

        if (false === \is_array($controllerMeta)) {
            return;
        }

        [$controller, $method] = $controllerMeta;

        $this->evictService->processEvent($controller, $method, $event);
        $this->cacheableService->processEvent($controller, $method, $event);
    }
}
