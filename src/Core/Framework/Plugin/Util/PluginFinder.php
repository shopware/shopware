<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Util;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\CompletePackageInterface;
use Shopware\Core\Framework\Plugin\Composer\Factory;
use Shopware\Core\Framework\Plugin\Composer\PackageProvider;
use Shopware\Core\Framework\Plugin\Exception\ExceptionCollection;
use Shopware\Core\Framework\Plugin\Exception\PluginComposerJsonInvalidException;
use Shopware\Core\Framework\Plugin\Struct\PluginFromFileSystemStruct;
use Symfony\Component\Finder\Finder;

class PluginFinder
{
    private const COMPOSER_TYPE = 'shopware-platform-plugin';
    private const SHOPWARE_PLUGIN_CLASS_EXTRA_IDENTIFIER = 'shopware-plugin-class';

    /**
     * @var PackageProvider
     */
    private $packageProvider;

    public function __construct(PackageProvider $packageProvider)
    {
        $this->packageProvider = $packageProvider;
    }

    /**
     * @return PluginFromFileSystemStruct[]
     */
    public function findPlugins(
        string $pluginDir,
        string $projectDir,
        ExceptionCollection $errors,
        IOInterface $composerIO
    ): array {
        return array_merge(
            $this->loadLocalPlugins($pluginDir, $composerIO, $errors),
            $this->loadVendorInstalledPlugins($projectDir, $composerIO, $errors)
        );
    }

    private function loadLocalPlugins(string $pluginDir, IOInterface $composerIO, ExceptionCollection $errors): array
    {
        $plugins = [];
        $filesystemPlugins = (new Finder())
            ->directories()
            ->depth(0)
            ->in($pluginDir)
            ->sortByName()
            ->getIterator();

        foreach ($filesystemPlugins as $filesystemPlugin) {
            $pluginPath = $filesystemPlugin->getRealPath();

            try {
                $package = $this->packageProvider->getPluginComposerPackage($pluginPath, $composerIO);
            } catch (PluginComposerJsonInvalidException $e) {
                $errors->add($e);

                continue;
            }

            if (!$this->isShopwarePluginType($package) || !$this->isPluginComposerValid($package)) {
                $this->addError($pluginPath, $errors);

                continue;
            }

            $pluginName = $this->getPluginNameFromPackage($package);

            $plugins[$pluginName] = (new PluginFromFileSystemStruct())->assign([
                'baseClass' => $pluginName,
                'path' => $filesystemPlugin->getPathname(),
                'managedByComposer' => false,
                'composerPackage' => $package,
            ]);
        }

        return $plugins;
    }

    private function isShopwarePluginType(CompletePackageInterface $package): bool
    {
        return $package->getType() === self::COMPOSER_TYPE;
    }

    private function isPluginComposerValid(CompletePackageInterface $package): bool
    {
        return isset($package->getExtra()[self::SHOPWARE_PLUGIN_CLASS_EXTRA_IDENTIFIER])
            && $package->getExtra()[self::SHOPWARE_PLUGIN_CLASS_EXTRA_IDENTIFIER] !== '';
    }

    private function getPluginNameFromPackage(CompletePackageInterface $pluginPackage): string
    {
        return $pluginPackage->getExtra()[self::SHOPWARE_PLUGIN_CLASS_EXTRA_IDENTIFIER];
    }

    private function loadVendorInstalledPlugins(
        string $projectDir,
        IOInterface $composerIO,
        ExceptionCollection $errors
    ): array {
        $plugins = [];
        $composer = Factory::createComposer($projectDir, $composerIO);

        /** @var CompletePackageInterface[] $composerPackages */
        $composerPackages = $composer
            ->getRepositoryManager()
            ->getLocalRepository()
            ->getPackages();

        foreach ($composerPackages as $composerPackage) {
            if (!$this->isShopwarePluginType($composerPackage)) {
                continue;
            }

            $pluginPath = $this->getVendorPluginPath($composerPackage, $composer);
            if (!$this->isPluginComposerValid($composerPackage)) {
                $this->addError($pluginPath, $errors);

                continue;
            }

            $pluginBaseClass = $this->getPluginNameFromPackage($composerPackage);
            $plugins[$pluginBaseClass] = (new PluginFromFileSystemStruct())->assign([
                'baseClass' => $pluginBaseClass,
                'path' => $pluginPath,
                'managedByComposer' => true,
                'composerPackage' => $composerPackage,
            ]);
        }

        return $plugins;
    }

    private function getVendorPluginPath(CompletePackageInterface $pluginPackage, Composer $composer): string
    {
        return $composer->getConfig()->get('vendor-dir') . '/' . $pluginPackage->getPrettyName();
    }

    private function addError(string $pluginPath, ExceptionCollection $errors): void
    {
        $errors->add(new PluginComposerJsonInvalidException(
            $pluginPath . '/composer.json',
            [
                sprintf(
                    'Plugin composer.json has invalid "type" (must be "%s"), or invalid "extra/%s" value',
                    self::COMPOSER_TYPE,
                    self::SHOPWARE_PLUGIN_CLASS_EXTRA_IDENTIFIER
                ),
            ]
        ));
    }
}
