<?php

namespace Pada\ResponseCacheBundle\Tests;

use Pada\ResponseCacheBundle\ResponseCacheBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel;

class TestKernel extends Kernel
{

    public function registerBundles()
    {
        return [
            new FrameworkBundle(),
            new ResponseCacheBundle()
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        // TODO: Implement registerContainerConfiguration() method.
    }
}
