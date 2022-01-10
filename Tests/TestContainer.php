<?php

namespace Pada\ResponseCacheBundle\Tests;

use Psr\Container\ContainerInterface;

class TestContainer implements ContainerInterface
{
    private array $services;

    public function __construct(array $services)
    {
        $this->services = $services;
    }

    public function get(string $id)
    {
        return $this->services[$id] ?? null;
    }

    public function has(string $id)
    {
        return null !== ($this->services[$id] ?? null);
    }
}
