<?php
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

namespace Shopware\Storefront\Theme;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Finder\Finder;

class LessVariablesCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $presets = array_merge(
            $container->getParameter('assetic.filter.lessphp.presets'),
            $this->getThemeVariables($container),
            $this->getSystemVariables($container),
            $this->getPluginImports($container)
        );

        $lessphpFilter = $container->getDefinition('assetic.filter.lessphp');
        $lessphpFilter->addArgument(new Reference('kernel'));
        $lessphpFilter->addMethodCall('setPresets', [$presets]);
    }

    private function getSystemVariables(ContainerBuilder $container): array
    {
        return [
            'pluginDirectory' => sprintf('"%s"', $container->getParameter('kernel.plugin_dir')),
            'pluginLess' => sprintf('"%s/plugins.less"', $container->getParameter('kernel.cache_dir')),
        ];
    }

    private function getThemeVariables(ContainerBuilder $container)
    {
        return [
            // todo: read theme config and return it as key/value array
        ];
    }

    private function getPluginImports(ContainerBuilder $container): array
    {
        $presets = [];
        $activePlugins = $container->getParameter('kernel.active_plugins');
        $pluginDirectory = $container->getParameter('kernel.plugin_dir');

        $finder = new Finder();
        $pluginDirectories = $finder->directories()->depth(0)->in($pluginDirectory)->getIterator();

        foreach ($pluginDirectories as $pluginDirectory) {
            $path = 'none';

            if (array_key_exists($pluginDirectory->getFilename(), $activePlugins) && file_exists($pluginDirectory->getRealPath() . '/Resources/public/less/all.less')) {
                $path = sprintf('"%s"', $pluginDirectory->getRealPath());
            }

            $presets[$pluginDirectory->getFilename()] = $path;
        }

        return $presets;
    }
}
