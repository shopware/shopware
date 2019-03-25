<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Helper;

use Shopware\Core\Framework\Plugin\Composer\Factory;
use Shopware\Core\Framework\Plugin\Struct\PluginFromFileSystemStruct;
use Symfony\Component\Finder\Finder;

class PluginFinder
{
    /**
     * @return PluginFromFileSystemStruct[]
     */
    public static function findPlugins(string $pluginDir, string $projectDir): array
    {
        $pluginsFromFileSystem = [];

        $filesystemPlugins = (new Finder())->directories()->depth(0)->in($pluginDir)->getIterator();
        foreach ($filesystemPlugins as $filesystemPlugin) {
            $pluginFromFileSystem = new PluginFromFileSystemStruct();
            $pluginFromFileSystem->assign([
                'name' => $filesystemPlugin->getFilename(),
                'path' => $filesystemPlugin->getPathname(),
                'managedByComposer' => false,
            ]);
            $pluginsFromFileSystem[] = $pluginFromFileSystem;
        }

        $composer = Factory::createComposer($projectDir);

        $composerPackages = $composer->getRepositoryManager()->getLocalRepository()->getPackages();
        foreach ($composerPackages as $composerPackage) {
            if ($composerPackage->getType() === 'shopware-plugin') {
                $pluginName = $composerPackage->getExtra()['installer-name'];
                $pluginPath = $composer->getConfig()->get('vendor-dir') . '/' . $composerPackage->getPrettyName();
                $pluginFromFileSystem = new PluginFromFileSystemStruct();
                $pluginFromFileSystem->assign([
                    'name' => $pluginName,
                    'path' => $pluginPath,
                    'managedByComposer' => true,
                ]);
                $pluginsFromFileSystem[] = $pluginFromFileSystem;
            }
        }

        return $pluginsFromFileSystem;
    }
}
