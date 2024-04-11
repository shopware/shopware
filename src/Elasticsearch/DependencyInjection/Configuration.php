<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\DependencyInjection;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

#[Package('core')]
class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('elasticsearch');

        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
                ->booleanNode('enabled')->end()
                ->booleanNode('indexing_enabled')->end()
                ->integerNode('indexing_batch_size')->defaultValue(100)->end()
                ->scalarNode('hosts')->end()
                ->scalarNode('index_prefix')->end()
                ->scalarNode('throw_exception')->end()
                ->arrayNode('ssl')
                    ->children()
                        ->scalarNode('cert_path')->end()
                        ->scalarNode('cert_password')->end()
                        ->scalarNode('cert_key_path')->end()
                        ->scalarNode('cert_key_password')->end()
                        ->booleanNode('verify_server_cert')->defaultValue(true)->end()
                    ->end()
                ->end()
                ->arrayNode('index_settings')->variablePrototype()->end()->end()
                ->arrayNode('analysis')->performNoDeepMerging()->variablePrototype()->end()->end()
                ->arrayNode('language_analyzer_mapping')->defaultValue([])->scalarPrototype()->end()->end()
                ->arrayNode('dynamic_templates')->performNoDeepMerging()->variablePrototype()->end()->end()
                ->arrayNode('product')
                    ->children()
                        ->arrayNode('custom_fields_mapping')
                            ->variablePrototype()->end()
                        ->end()
                        ->booleanNode('exclude_source')->end()
                    ->end()
                ->end()
                ->arrayNode('search')
                    ->children()
                        ->scalarNode('timeout')->end()
                        ->integerNode('term_max_length')->end()
                        ->scalarNode('search_type')->end()
                    ->end()
                ->end()
                ->arrayNode('administration')
                    ->children()
                        ->scalarNode('hosts')->end()
                        ->booleanNode('enabled')->end()
                        ->booleanNode('refresh_indices')->end()
                        ->scalarNode('index_prefix')->end()
                        ->arrayNode('index_settings')->variablePrototype()->end()->end()
                        ->arrayNode('analysis')->performNoDeepMerging()->variablePrototype()->end()->end()
                        ->arrayNode('dynamic_templates')->performNoDeepMerging()->variablePrototype()->end()->end()
                        ->arrayNode('search')
                            ->children()
                                ->scalarNode('timeout')->end()
                                ->integerNode('term_max_length')->end()
                                ->scalarNode('search_type')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
