<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Helper;

use Composer\Factory;
use Composer\IO\NullIO;
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

        $composer = (new Factory())->createComposer(
            new NullIO(),
            $projectDir . '/composer.json',
            false,
            $projectDir
        );

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
