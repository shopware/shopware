<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder();

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->root('shopware');

        $rootNode
            ->children()
                ->append($this->createFilesystemSection())
                ->append($this->createCdnSection())
                ->append($this->createApiSection())
            ->end()
        ;

        return $treeBuilder;
    }

    private function createFilesystemSection()
    {
        $treeBuilder = new TreeBuilder();

        /** @var ArrayNodeDefinition $node */
        $node = $treeBuilder->root('filesystem');

        $node
            ->children()
                ->arrayNode('private')
                    ->performNoDeepMerging()
                    ->children()
                        ->scalarNode('type')->end()
                        ->variableNode('config')->end()
                    ->end()
                ->end()
                ->arrayNode('public')
                    ->performNoDeepMerging()
                    ->children()
                        ->scalarNode('type')->end()
                        ->variableNode('config')->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $node;
    }

    private function createCdnSection()
    {
        $treeBuilder = new TreeBuilder();

        /** @var ArrayNodeDefinition $node */
        $node = $treeBuilder->root('cdn');

        $node
            ->children()
                ->scalarNode('url')->end()
                ->scalarNode('strategy')->end()
            ->end()
            ;

        return $node;
    }

    private function createApiSection()
    {
        $treeBuilder = new TreeBuilder();

        /** @var ArrayNodeDefinition $node */
        $node = $treeBuilder->root('api');

        $node
            ->children()
            ->arrayNode('allowed_limits')
            ->prototype('scalar')->end()
            ->end()
            ->integerNode('max_limit')->end()
            ->end()
        ;

        return $node;
    }
}
