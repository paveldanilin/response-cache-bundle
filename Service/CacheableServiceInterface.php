<?php

namespace Pada\ResponseCacheBundle\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

interface CacheableServiceInterface
{
    /**
     * @param mixed $controller
     * @param string $method
     * @param ControllerEvent $event
     */
    public function processEvent($controller, string $method, ControllerEvent $event): void;

    public function updateCacheIfNeeded(ResponseEvent $event): void;

    public function warmUpSystemCache(string $scanDir): void;

    public function releaseLockIfNeeded(Request $request): void;
}
