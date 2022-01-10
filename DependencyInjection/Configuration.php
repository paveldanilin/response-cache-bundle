<?php

namespace Pada\ResponseCacheBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('paveldanilin_response_cache');

        /** @phpstan-ignore-next-line */
        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('lock')
                    ->children()
                        ->scalarNode('store')->end()
                    ->end()
                ->end() // lock
                ->arrayNode('controller')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('dir')->defaultValue('%kernel.project_dir%/src')->end()
                    ->end()
                ->end() // controller
            ->end();

        return $treeBuilder;
    }
}
