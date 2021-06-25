<?php declare(strict_types=1);

namespace Shopware\Storefront\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('storefront');

        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('csrf')
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultTrue()
                        ->end()
                        ->enumNode('mode')
                            ->values(['twig', 'ajax'])
                            ->defaultValue('twig')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('htmlPurifier')
                    ->setDeprecated()
                    ->children()
                        ->variableNode('cacheDir')
                            ->setDeprecated()
                        ->end()
                        ->booleanNode('cacheEnabled')
                            ->setDeprecated()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('reverse_proxy')
                    ->children()
                        ->booleanNode('enabled')->end()
                        ->arrayNode('hosts')->scalarPrototype()->end()->end()
                        ->integerNode('max_parallel_invalidations')->defaultValue(2)->end()
                        ->scalarNode('redis_url')->end()
                        ->scalarNode('ban_method')->defaultValue('BAN')->end()
                    ->end()
                ->end()
                ->arrayNode('http_cache')
                    ->children()
                    ->arrayNode('ignored_url_parameters')->scalarPrototype()->end()->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
