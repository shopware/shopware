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

namespace Shopware\Storefront;

use Assetic\Asset\AssetCollection;
use Assetic\Asset\FileAsset;
use Assetic\Asset\StringAsset;
use Shopware\Storefront\Theme\LessVariablesCompilerPass;
use Shopware\Storefront\Theme\SourceMapCompilerPass;
use Shopware\Storefront\Theme\Theme;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\Finder\Finder;

class Storefront extends Theme
{
    protected $name = 'Storefront';

    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection/'));
        $loader->load('services.xml');

        $this->registerCompilerPasses($container);
        $this->registerNamedAssets($container);
    }

    private function registerCompilerPasses(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new LessVariablesCompilerPass());

        if ($container->getParameter('kernel.debug')) {
            $container->addCompilerPass(new SourceMapCompilerPass());
        }
    }

    private function registerNamedAssets(ContainerBuilder $container): void
    {
        $this->registerNamedJavascripts($container);
        $this->registerNamedStylesheets($container);
    }

    private function registerNamedJavascripts(ContainerBuilder $container): void
    {
        $activePlugins = $container->getParameter('kernel.active_plugins');

        $paths = array_column($activePlugins, 'path');
        $paths = array_map(
            function (string $path) {
                return $path . '/Resources/public';
            },
            $paths
        );

        $paths = array_filter($paths, function ($path) {
            return file_exists($path);
        });

        $collection = new AssetCollection();

        if (count($paths)) {
            $finder = new Finder();
            $files = $finder->files()->in($paths)->name('*.js')->getIterator();

            /** @var \SplFileInfo $file */
            foreach ($files as $file) {
                $collection->add(new FileAsset($file->getRealPath()));
            }
        }

        $definition = new Definition(StringAsset::class, [$collection->dump()]);
        $definition->addTag('assetic.asset', ['alias' => 'plugin_javascripts', 'output' => 'js/plugin.js']);
        $definition->addMethodCall('setLastModified', [$collection->getLastModified()]);
        $definition->addMethodCall('setTargetPath', ['js/plugin.js']);

        $container->setDefinition('shopware.storefront.theme.plugin_javascripts', $definition);
    }

    private function registerNamedStylesheets(ContainerBuilder $container): void
    {
        $collection = new AssetCollection();
        $activePlugins = $container->getParameter('kernel.active_plugins');

        foreach ($activePlugins as $plugin) {
            $lessFile = $plugin['path'] . '/Resources/public/less/all.less';
            if (!file_exists($lessFile)) {
                continue;
            }

            $collection->add(new FileAsset($lessFile));
        }

        $definition = new Definition(StringAsset::class, [$collection->dump()]);
        $definition->addTag('assetic.asset', ['alias' => 'plugin_stylesheets', 'output' => 'css/plugin.css']);
        $definition->addMethodCall('setLastModified', [$collection->getLastModified()]);
        $definition->addMethodCall('setTargetPath', ['css/plugin.css']);

        $container->setDefinition('shopware.storefront.theme.plugin_stylesheets', $definition);
    }
}
