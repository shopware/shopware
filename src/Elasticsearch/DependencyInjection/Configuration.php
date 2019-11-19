<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('elasticsearch');

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
                ->booleanNode('enabled')->end()
                ->booleanNode('indexing_enabled')->end()
                ->scalarNode('hosts')->defaultValue('')->end()
                ->scalarNode('index_prefix')->defaultValue('sw')->end()
            ->end();

        return $treeBuilder;
    }
}
