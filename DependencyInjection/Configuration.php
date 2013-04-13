<?php

namespace Lyrixx\Bundle\LifestreamBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('lyrixx_lifestream');

       $rootNode
            ->children()
                ->arrayNode('lifestream')
                    ->prototype('array')
                    ->children()
                        ->scalarNode('service')
                            ->isRequired()->cannotBeEmpty()
                        ->end()
                        ->arrayNode('args')
                            ->requiresAtLeastOneElement()
                            ->prototype('scalar')->end()
                        ->end()
                        ->arrayNode('filters')
                            ->prototype('scalar')->end()
                        ->end()
                        ->arrayNode('formatters')
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
