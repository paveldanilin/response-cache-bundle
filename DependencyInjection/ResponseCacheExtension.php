<?php

namespace Pada\ResponseCacheBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

class ResponseCacheExtension extends Extension implements CompilerPassInterface
{
    private array $bundleConfig;

    public function load(array $configs, ContainerBuilder $container): void
    {
        $this->bundleConfig = $this->processConfiguration(new Configuration(), $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $cacheWarmer = $container->getDefinition('response_cache_bundle_cache_warmer');
        $cacheWarmer->replaceArgument(0, $this->bundleConfig['controller']['dir'] ?? Configuration::DEFAULT_CONTROLLER_DIR);
        $cacheWarmer->addMethodCall('setLogger', [new Reference('logger')]);
    }

    public function process(ContainerBuilder $container): void
    {
        $lockResponseCacheStoreId = Configuration::DEFAULT_LOCK_STORE_SERVICE_ID;
        if (!$container->has($lockResponseCacheStoreId)) {
            $container->register($lockResponseCacheStoreId, FlockStore::class);
        }

        $lockResponseCacheFactoryId = $this->bundleConfig['lock']['factory'] ?? Configuration::DEFAULT_LOCK_FACTORY_SERVICE_ID;
        if (!$container->has($lockResponseCacheFactoryId)) {
            $container->register($lockResponseCacheFactoryId, LockFactory::class)
                ->setArgument(0, new Reference($lockResponseCacheStoreId));
        }

        $cacheableDefinition = $container->getDefinition('cacheable_service');
        $cacheableDefinition->replaceArgument(5, new Reference($lockResponseCacheFactoryId));
        $cacheableDefinition->addMethodCall('setLogger', [new Reference('logger')]);
    }
}
