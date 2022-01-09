<?php


namespace Pada\ResponseCacheBundle\EventListener;

use Pada\ResponseCacheBundle\Service\CacheableServiceInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;


class ControllerListener
{
    private CacheableServiceInterface $cacheableService;

    public function __construct(CacheableServiceInterface $service)
    {
        $this->cacheableService = $service;
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

        $this->cacheableService->processEvent($controller, $method, $event);
    }
}
