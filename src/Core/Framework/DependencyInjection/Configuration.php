<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('shopware');

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
                ->append($this->createFilesystemSection())
                ->append($this->createCdnSection())
                ->append($this->createApiSection())
                ->append($this->createStoreSection())
                ->append($this->createAdminWorkerSection())
                ->append($this->createCacheSection())
                ->append($this->createAutoUpdateSection())
                ->append($this->createSitemapSection())
            ->end();

        return $treeBuilder;
    }

    private function createFilesystemSection(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('filesystem');

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();
        $rootNode
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
            ->end();

        return $rootNode;
    }

    private function createCdnSection(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('cdn');

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
                ->scalarNode('url')->end()
                ->scalarNode('strategy')->end()
            ->end();

        return $rootNode;
    }

    private function createApiSection(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('api');

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
            ->arrayNode('allowed_limits')
                ->prototype('scalar')->end()
            ->end()
            ->integerNode('max_limit')->end()
            ->arrayNode('api_browser')
                ->children()
                ->booleanNode('auth_required')
                    ->defaultTrue()
                ->end()
            ->end()
            ->end();

        return $rootNode;
    }

    private function createStoreSection(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('store');

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
            ->booleanNode('frw')->end()
            ->end();

        return $rootNode;
    }

    private function createAdminWorkerSection(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('admin_worker');

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
                ->arrayNode('transports')
                    ->prototype('scalar')->end()
                ->end()
                ->integerNode('poll_interval')
                    ->defaultValue(30)
                ->end()
                ->booleanNode('enable_admin_worker')
                    ->defaultValue(true)
                ->end()

            ->end();

        return $rootNode;
    }

    private function createCacheSection(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('cache');

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
                ->arrayNode('entity_cache')
                    ->children()
                        ->integerNode('expiration_time')->min(0)->end()
                        ->booleanNode('enabled')->end()
                    ->end()
                ->end()
            ->end();

        return $rootNode;
    }

    private function createAutoUpdateSection(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('auto_update');

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
                ->booleanNode('enabled')->end()
            ->end()
        ;

        return $rootNode;
    }

    private function createSitemapSection(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('sitemap');

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
                ->arrayNode('custom_urls')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('url')->end()
                            ->scalarNode('lastMod')->end()
                            ->enumNode('changeFreq')
                                ->values([
                                    'always',
                                    'hourly',
                                    'daily',
                                    'weekly',
                                    'monthly',
                                    'yearly',
                                ])
                            ->end()
                            ->floatNode('priority')->end()
                            ->scalarNode('salesChannelId')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('excluded_urls')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('resource')->end()
                            ->scalarNode('identifier')->end()
                            ->scalarNode('salesChannelId')->end()
                        ->end()
                    ->end()
                ->end()
                ->integerNode('batchsize')
                    ->min(1)
                    ->defaultValue(100)
                ->end()
            ->end();

        return $rootNode;
    }
}
