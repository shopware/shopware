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
                ->scalarNode('hosts')->defaultValue(getenv('SHOPWARE_ES_HOSTS'))->end()
                ->booleanNode('enabled')->defaultValue(getenv('SHOPWARE_ES_ENABLED'))->end()
                ->booleanNode('indexing_enabled')->defaultValue(getenv('SHOPWARE_ES_INDEXING_ENABLED'))->end()
                ->scalarNode('index_prefix')->defaultValue(getenv('SHOPWARE_ES_INDEX_PREFIX'))->end()
            ->end();

        return $treeBuilder;
    }
}
