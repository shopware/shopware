<?php declare(strict_types=1);

namespace Shopware\Storefront\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('storefront');

        /** @var ArrayNodeDefinition $rootNode */
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
                    ->children()
                        ->variableNode('cacheDir')
                            ->defaultValue('%kernel.cache_dir%')
                        ->end()
                        ->booleanNode('cacheEnabled')
                            ->defaultTrue()
                        ->end()
                    ->end()
                ->end()
        ->end();

        return $treeBuilder;
    }
}
