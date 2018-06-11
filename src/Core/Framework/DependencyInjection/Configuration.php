<?php
declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Core\Framework\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('shopware');

        $rootNode
            ->children()
                ->append($this->createFilesystemSection())
                ->append($this->createCdnSection())
                ->append($this->createCsrfSection())
                ->append($this->createSnippetSection())
                ->append($this->createErrorHandlerSection())
                ->append($this->createElasticsearchSection())
                ->append($this->createFrontSection())
                ->append($this->createStoreSection())
                ->append($this->createTemplateSection())
                ->append($this->createMailSection())
                ->append($this->createHttpCacheSection())
                ->append($this->createSessionSection())
                ->append($this->createPhpSettingsSection())
                ->append($this->createCacheSection())
                ->append($this->createHookSection())
                ->append($this->createModelSection())
                ->append($this->createBackendSessionSection())
                ->scalarNode('plugin_directory')->end()
                ->arrayNode('trustedproxies')->end()
                ->variableNode('custom')->end()
                ->variableNode('config')->end()
            ->end();

        return $treeBuilder;
    }

    private function createFilesystemSection()
    {
        $treeBuilder = new TreeBuilder();
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

    private function createSnippetSection()
    {
        $treeBuilder = new TreeBuilder();
        $node = $treeBuilder->root('snippet');

        $node
            ->children()
                ->booleanNode('readFromDb')->end()
                ->booleanNode('writeToDb')->end()
                ->booleanNode('readFromIni')->end()
                ->booleanNode('writeToIni')->end()
                ->booleanNode('showSnippetPlaceholder')->end()
            ->end()
        ;

        return $node;
    }

    private function createCdnSection()
    {
        $treeBuilder = new TreeBuilder();
        $node = $treeBuilder->root('cdn');

        $node
            ->children()
                ->scalarNode('url')->end()
                ->scalarNode('strategy')->end()
            ->end()
            ;

        return $node;
    }

    private function createCsrfSection()
    {
        $treeBuilder = new TreeBuilder();
        $node = $treeBuilder->root('csrfProtection');

        $node
            ->children()
                ->booleanNode('frontend')->end()
                ->booleanNode('backend')->end()
            ->end()
            ;

        return $node;
    }

    private function createErrorHandlerSection()
    {
        $treeBuilder = new TreeBuilder();
        $node = $treeBuilder->root('errorHandler');

        $node
            ->children()
                ->booleanNode('throwOnRecoverableError')->end()
            ->end()
            ;

        return $node;
    }

    private function createElasticsearchSection()
    {
        $treeBuilder = new TreeBuilder();
        $node = $treeBuilder->root('elasticsearch');

        $node
            ->children()
                ->scalarNode('prefix')->end()
                ->booleanNode('enabled')->end()
                ->booleanNode('write_backlog')->end()
                ->scalarNode('number_of_replicas')->end()
                ->scalarNode('number_of_shards')->end()
                ->scalarNode('wait_for_status')->end()
                ->arrayNode('client')
                    ->children()
                        ->arrayNode('hosts')
                            ->performNoDeepMerging()
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $node;
    }

    private function createFrontSection()
    {
        $treeBuilder = new TreeBuilder();
        $node = $treeBuilder->root('front');

        $node
            ->children()
                ->booleanNode('noErrorHandler')->end()
                ->booleanNode('throwExceptions')->end()
                ->booleanNode('disableOutputBuffering')->end()
                ->booleanNode('showException')->end()
                ->scalarNode('charset')->end()
            ->end()
        ;

        return $node;
    }

    private function createStoreSection()
    {
        $treeBuilder = new TreeBuilder();
        $node = $treeBuilder->root('store');

        $node
            ->children()
                ->scalarNode('apiEndpoint')->end()
            ->end()
        ;

        return $node;
    }

    private function createTemplateSection()
    {
        $treeBuilder = new TreeBuilder();
        $node = $treeBuilder->root('template');

        $node
            ->children()
                ->booleanNode('compileCheck')->end()
                ->booleanNode('compileLocking')->end()
                ->booleanNode('useSubDirs')->end()
                ->booleanNode('forceCompile')->end()
                ->booleanNode('useIncludePath')->end()
                ->scalarNode('charset')->end()
                ->booleanNode('forceCache')->end()
                ->scalarNode('cacheDir')->end()
                ->scalarNode('compileDir')->end()
            ->end()
        ;

        return $node;
    }

    private function createMailSection()
    {
        $treeBuilder = new TreeBuilder();
        $node = $treeBuilder->root('mail');

        $node
            ->children()
                ->scalarNode('charset')->end()
            ->end()
        ;

        return $node;
    }

    private function createHttpCacheSection()
    {
        $treeBuilder = new TreeBuilder();
        $node = $treeBuilder->root('httpcache');

        $node
            ->children()
                ->booleanNode('enabled')->end()
                ->booleanNode('lookup_optimization')->end()
                ->booleanNode('debug')->end()
                ->integerNode('default_ttl')->end()
                ->arrayNode('private_headers')
                    ->prototype('scalar')->end()
                ->end()
                ->booleanNode('allow_reload')->end()
                ->booleanNode('allow_revalidate')->end()
                ->integerNode('stale_while_revalidate')->end()
                ->booleanNode('stale_if_error')->end()
                ->scalarNode('cache_dir')->end()
                ->arrayNode('cache_cookies')
                    ->prototype('scalar')->end()
                ->end()
            ->end()
        ;

        return $node;
    }

    private function createSessionSection()
    {
        $treeBuilder = new TreeBuilder();
        $node = $treeBuilder->root('session');

        $node
            ->children()
                ->integerNode('cookie_lifetime')->end()
                ->booleanNode('cookie_httponly')->end()
                ->integerNode('gc_probability')->end()
                ->integerNode('gc_divisor')->end()
                ->scalarNode('save_handler')->end()
                ->scalarNode('save_path')->end()
                ->booleanNode('use_trans_sid')->end()
                ->booleanNode('locking')->end()
            ->end()
        ;

        return $node;
    }

    private function createPhpSettingsSection()
    {
        $treeBuilder = new TreeBuilder();
        $node = $treeBuilder->root('phpsettings');

        $node
            ->children()
                ->scalarNode('error_reporting')->end()
                ->booleanNode('display_errors')->end()
                ->arrayNode('date')
                    ->children()
                        ->scalarNode('timezone')->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $node;
    }

    private function createCacheSection()
    {
        $treeBuilder = new TreeBuilder();
        $node = $treeBuilder->root('cache');

        $node
            ->children()
                ->scalarNode('backend')->end()
                ->arrayNode('backendOptions')
                    ->children()
                        ->scalarNode('hashed_directory_perm')->end()
                        ->scalarNode('cache_file_perm')->end()
                        ->scalarNode('hashed_directory_level')->end()
                        ->scalarNode('cache_dir')->end()
                        ->scalarNode('file_name_prefix')->end()
                    ->end()
                ->end()
                ->arrayNode('frontendOptions')
                    ->children()
                        ->booleanNode('automatic_serialization')->end()
                        ->integerNode('automatic_cleaning_factor')->end()
                        ->integerNode('lifetime')->end()
                        ->scalarNode('cache_id_prefix')->end()
                ->end()
            ->end()
        ;

        return $node;
    }

    private function createHookSection()
    {
        $treeBuilder = new TreeBuilder();
        $node = $treeBuilder->root('hook');

        $node
            ->children()
                ->scalarNode('proxyDir')->end()
                ->scalarNode('proxyNamespace')->end()
            ->end()
        ;

        return $node;
    }

    private function createModelSection()
    {
        $treeBuilder = new TreeBuilder();
        $node = $treeBuilder->root('model');

        $node
            ->children()
                ->booleanNode('autoGenerateProxyClasses')->end()
                ->scalarNode('attributeDir')->end()
                ->scalarNode('proxyDir')->end()
                ->scalarNode('proxyNamespace')->end()
                ->scalarNode('cacheProvider')->end()
                ->scalarNode('cacheNamespace')->end()
            ->end()
        ;

        return $node;
    }

    private function createBackendSessionSection()
    {
        $treeBuilder = new TreeBuilder();
        $node = $treeBuilder->root('backendsession');

        $node
            ->children()
                ->scalarNode('name')->end()
                ->integerNode('cookie_lifetime')->end()
                ->booleanNode('cookie_httponly')->end()
                ->integerNode('use_trans_sid')->end()
                ->booleanNode('locking')->end()
            ->end()
        ;

        return $node;
    }
}
