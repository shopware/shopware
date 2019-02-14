<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Helper;

use Shopware\Core\Framework\Plugin\Composer\Factory;
use Symfony\Component\Finder\Finder;

class PluginFinder
{
    /**
     * @return string[]
     */
    public static function findPlugins(string $pluginDir, string $projectDir): array
    {
        $filesystemPlugins = (new Finder())->directories()->depth(0)->in($pluginDir)->getIterator();

        $pluginNamesWithPaths = [];
        foreach ($filesystemPlugins as $filesystemPlugin) {
            $pluginNamesWithPaths[$filesystemPlugin->getFilename()] = $filesystemPlugin->getPathname();
        }

        $composer = Factory::createComposer($projectDir);

        $composerPackages = $composer->getRepositoryManager()->getLocalRepository()->getPackages();
        foreach ($composerPackages as $composerPackage) {
            if ($composerPackage->getType() === 'shopware-plugin') {
                $pluginName = $composerPackage->getExtra()['installer-name'];
                $pluginPath = $composer->getConfig()->get('vendor-dir') . '/' . $composerPackage->getPrettyName();
                $pluginNamesWithPaths[$pluginName] = $pluginPath;
            }
        }

        return $pluginNamesWithPaths;
    }
}
