<?php

namespace Pada\ResponseCacheBundle\Service;

use Symfony\Component\HttpKernel\Event\ControllerEvent;

interface EvictServiceInterface
{
    /**
     * @param mixed $controller
     * @param string $method
     * @param ControllerEvent $event
     */
    public function processEvent($controller, string $method, ControllerEvent $event): void;
    public function warmUpSystemCache(string $scanDir): void;
}
