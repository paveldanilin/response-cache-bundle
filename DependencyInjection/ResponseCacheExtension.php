<?php

namespace Pada\ResponseCacheBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Reference;

class ResponseCacheExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $cacheWarmer = $container->getDefinition('response_cache_bundle_cache_warmer');
        $cacheWarmer->replaceArgument(0, $config['controller']['dir']);

        $cacheableDefinition = $container->getDefinition('cacheable_service');
        $cacheableDefinition->replaceArgument(6, $config['lock']['store'] ?? 'flock');
        $cacheableDefinition->addMethodCall('setLogger', [new Reference('logger')]);
    }
}
