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
