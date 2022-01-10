<?php

namespace Pada\ResponseCacheBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

class ResponseCacheExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $cacheWarmer = $container->getDefinition('response_cache_bundle_cache_warmer');
        $cacheWarmer->replaceArgument(0, $config['controller']['dir']);
        $cacheWarmer->addMethodCall('setLogger', [new Reference('logger')]);


        $lockResponseCacheStoreId = 'lock.response.cache.store';
        if (!$container->has($lockResponseCacheStoreId)) {
            $container->register($lockResponseCacheStoreId, FlockStore::class);
        }

        $lockResponseCacheFactoryId = $config['lock']['factory'] ?? 'lock.response.cache.factory';
        if (!$container->has($lockResponseCacheFactoryId)) {
            $container->register($lockResponseCacheFactoryId, LockFactory::class)
                ->setArgument(0, new Reference($lockResponseCacheStoreId));
        }

        $cacheableDefinition = $container->getDefinition('cacheable_service');
        $cacheableDefinition->replaceArgument(5, new Reference($lockResponseCacheFactoryId));
        $cacheableDefinition->addMethodCall('setLogger', [new Reference('logger')]);
    }
}
