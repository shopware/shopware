<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Util;

use Composer\Composer;
use Composer\Package\PackageInterface;
use Shopware\Core\Framework\Plugin\Composer\Factory;
use Shopware\Core\Framework\Plugin\Struct\PluginFromFileSystemStruct;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class PluginFinder
{
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
            $pluginName = $this->determinePluginName($filesystemPlugin);

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
        return $package->getType() === 'shopware-platform-plugin'
            && isset($package->getExtra()['installer-name'])
            && $package->getExtra()['installer-name'] !== '';
    }

    private function getPluginNameFromPackage(PackageInterface $pluginPackage): string
    {
        return $pluginPackage->getExtra()['installer-name'];
    }

    private function getVendorPluginPath(PackageInterface $pluginPackage, Composer $composer): string
    {
        return $composer->getConfig()->get('vendor-dir') . '/' . $pluginPackage->getPrettyName();
    }

    private function determinePluginName(SplFileInfo $filesystemPlugin): string
    {
        $default = $filesystemPlugin->getFilename();

        try {
            $rootPackage = Factory::createComposer($filesystemPlugin->getRealPath())
                ->getPackage();
        } catch (\InvalidArgumentException $e) {
            return $default;
        }

        if (!$this->isShopwarePluginPackage($rootPackage)) {
            return $default;
        }

        return $this->getPluginNameFromPackage($rootPackage);
    }
}
