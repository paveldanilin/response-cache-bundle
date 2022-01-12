<?php

namespace Pada\ResponseCacheBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public const DEFAULT_LOCK_FACTORY_SERVICE_ID = 'lock.response_cache.factory';
    public const DEFAULT_LOCK_STORE_SERVICE_ID = 'lock.response_cache.store';
    public const DEFAULT_CONTROLLER_DIR = '%kernel.project_dir%/src';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('response_cache');

        /** @phpstan-ignore-next-line */
        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('lock')
                    ->children()
                        ->scalarNode('factory')->defaultValue(self::DEFAULT_LOCK_FACTORY_SERVICE_ID)->end()
                    ->end()
                ->end() // lock
                ->arrayNode('controller')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('dir')->defaultValue(self::DEFAULT_CONTROLLER_DIR)->end()
                    ->end()
                ->end() // controller
            ->end();

        return $treeBuilder;
    }
}
