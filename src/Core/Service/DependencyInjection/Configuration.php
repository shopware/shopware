<?php declare(strict_types=1);

namespace Shopware\Core\Service\DependencyInjection;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @internal
 */
#[Package('core')]
class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('service');

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('registry_url')
                    ->defaultValue('https://services.shopware.io/services.json')
                    ->end()
                ->booleanNode('enabled')
                    ->defaultValue(false)
                    ->end();

        return $treeBuilder;
    }
}
