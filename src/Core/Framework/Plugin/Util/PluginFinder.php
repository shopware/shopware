<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Util;

use Composer\Composer;
use Composer\Package\PackageInterface;
use Shopware\Core\Framework\Plugin\Composer\Factory;
use Shopware\Core\Framework\Plugin\Exception\PluginComposerJsonInvalidException;
use Shopware\Core\Framework\Plugin\Struct\PluginFromFileSystemStruct;
use Symfony\Component\Finder\Finder;

class PluginFinder
{
    private const COMPOSER_TYPE = 'shopware-platform-plugin';
    private const SHOPWARE_PLUGIN_CLASS_EXTRA_IDENTIFIER = 'shopware-plugin-class';

    /**
     * @return PluginFromFileSystemStruct[]
     */
    public function findPlugins(string $pluginDir, string $projectDir): array
    {
        return array_merge(
            $this->loadLocalPlugins($pluginDir),
            $this->loadVendorInstalledPlugins($projectDir)
        );
    }

    private function loadLocalPlugins(string $pluginDir): array
    {
        $plugins = [];
        $filesystemPlugins = (new Finder())
            ->directories()
            ->depth(0)
            ->in($pluginDir)
            ->sortByName()
            ->getIterator();

        foreach ($filesystemPlugins as $filesystemPlugin) {
            $pluginName = $this->determinePluginName($filesystemPlugin->getRealPath());

            $plugins[] = (new PluginFromFileSystemStruct())->assign([
                'name' => $pluginName,
                'path' => $filesystemPlugin->getPathname(),
                'managedByComposer' => false,
            ]);
        }

        return $plugins;
    }

    private function loadVendorInstalledPlugins(string $projectDir): array
    {
        $plugins = [];
        $composer = Factory::createComposer($projectDir);

        $composerPackages = $composer
            ->getRepositoryManager()
            ->getLocalRepository()
            ->getPackages();

        foreach ($composerPackages as $composerPackage) {
            if ($this->isShopwarePluginPackage($composerPackage)) {
                $plugins[] = (new PluginFromFileSystemStruct())->assign([
                    'name' => $this->getPluginNameFromPackage($composerPackage),
                    'path' => $this->getVendorPluginPath($composerPackage, $composer),
                    'managedByComposer' => true,
                ]);
            }
        }

        return $plugins;
    }

    private function isShopwarePluginPackage(PackageInterface $package): bool
    {
        return $package->getType() === self::COMPOSER_TYPE
            && isset($package->getExtra()[self::SHOPWARE_PLUGIN_CLASS_EXTRA_IDENTIFIER])
            && $package->getExtra()[self::SHOPWARE_PLUGIN_CLASS_EXTRA_IDENTIFIER] !== '';
    }

    private function getPluginNameFromPackage(PackageInterface $pluginPackage): string
    {
        return $pluginPackage->getExtra()[self::SHOPWARE_PLUGIN_CLASS_EXTRA_IDENTIFIER];
    }

    private function getVendorPluginPath(PackageInterface $pluginPackage, Composer $composer): string
    {
        return $composer->getConfig()->get('vendor-dir') . '/' . $pluginPackage->getPrettyName();
    }

    private function determinePluginName(string $pluginPath): string
    {
        try {
            $rootPackage = Factory::createComposer($pluginPath)
                ->getPackage();
        } catch (\InvalidArgumentException $e) {
            throw new PluginComposerJsonInvalidException($pluginPath . '/composer.json', [$e->getMessage()]);
        }

        if (!$this->isShopwarePluginPackage($rootPackage)) {
            throw new PluginComposerJsonInvalidException(
                $pluginPath . '/composer.json',
                [
                    sprintf(
                        'Plugin composer.json has invalid "type" (must be "%s"), or invalid "extra/%s" value',
                        self::COMPOSER_TYPE,
                        self::SHOPWARE_PLUGIN_CLASS_EXTRA_IDENTIFIER
                    ),
                ]
            );
        }

        return $this->getPluginNameFromPackage($rootPackage);
    }
}
