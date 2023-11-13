<?php declare(strict_types=1);

namespace Shopware\Storefront\DependencyInjection;

use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\ConfigLoader\DatabaseAvailableThemeProvider;
use Shopware\Storefront\Theme\ConfigLoader\DatabaseConfigLoader;
use Shopware\Storefront\Theme\SeedingThemePathBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

#[Package('storefront')]
class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('storefront');

        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('reverse_proxy')
                    ->children()
                        ->booleanNode('enabled')->end()
                        ->booleanNode('use_varnish_xkey')->defaultFalse()->end()
                        ->arrayNode('hosts')->performNoDeepMerging()->scalarPrototype()->end()->end()
                        ->integerNode('max_parallel_invalidations')->defaultValue(2)->end()
                        ->scalarNode('redis_url')->end()
                        ->scalarNode('ban_method')->defaultValue('BAN')->end()
                        ->arrayNode('ban_headers')->performNoDeepMerging()->defaultValue([])->scalarPrototype()->end()->end()
                        ->arrayNode('purge_all')
                            ->children()
                                ->scalarNode('ban_method')->defaultValue('BAN')->end()
                                ->arrayNode('ban_headers')->performNoDeepMerging()->defaultValue([])->scalarPrototype()->end()->end()
                                ->arrayNode('urls')->performNoDeepMerging()->defaultValue(['/'])->scalarPrototype()->end()->end()
                            ->end()
                        ->end()
                        ->arrayNode('fastly')
                            ->children()
                                 ->booleanNode('enabled')->defaultFalse()->end()
                                 ->scalarNode('api_key')->defaultValue('')->end()
                                 ->scalarNode('instance_tag')->defaultValue('')->end()
                                 ->scalarNode('service_id')->defaultValue('')->end()
                                 ->scalarNode('soft_purge')->defaultValue('0')->end()
                                 ->scalarNode('tag_prefix')->defaultValue('')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('http_cache')
                    ->children()
                        ->scalarNode('stale_while_revalidate')->defaultValue(null)->end()
                        ->scalarNode('stale_if_error')->defaultValue(null)->end()
                        ->arrayNode('ignored_url_parameters')->scalarPrototype()->end()
                    ->end()
                ->end()
                ->end()
                ->arrayNode('theme')
                    ->children()
                        ->scalarNode('config_loader_id')->defaultValue(DatabaseConfigLoader::class)->end()
                        ->scalarNode('theme_path_builder_id')->defaultValue(SeedingThemePathBuilder::class)->end()
                        ->scalarNode('available_theme_provider')->defaultValue(DatabaseAvailableThemeProvider::class)->end()
                        ->integerNode('file_delete_delay')->defaultValue(900)->end()
                        ->BooleanNode('auto_prefix_css')->defaultFalse()->end()
            ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
