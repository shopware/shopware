<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Util;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\CompletePackageInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Composer\Factory;
use Shopware\Core\Framework\Plugin\Composer\PackageProvider;
use Shopware\Core\Framework\Plugin\Exception\ExceptionCollection;
use Shopware\Core\Framework\Plugin\Exception\PluginComposerJsonInvalidException;
use Shopware\Core\Framework\Plugin\Struct\PluginFromFileSystemStruct;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Finder\Finder;

#[Package('core')]
class PluginFinder
{
    final public const COMPOSER_TYPE = 'shopware-platform-plugin';
    private const SHOPWARE_PLUGIN_CLASS_EXTRA_IDENTIFIER = 'shopware-plugin-class';

    /**
     * @internal
     */
    public function __construct(private readonly PackageProvider $packageProvider)
    {
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
        $plugins = $this->loadLocalPlugins($pluginDir, $composerIO, $errors);

        return $this->enrichWithVendorPlugins($plugins, $projectDir, $composerIO, $errors);
    }

    /**
     * @return array<string, PluginFromFileSystemStruct>
     */
    private function loadLocalPlugins(string $pluginDir, IOInterface $composerIO, ExceptionCollection $errors): array
    {
        $plugins = [];

        try {
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
        } catch (DirectoryNotFoundException) {
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
            && $package->getExtra()[self::SHOPWARE_PLUGIN_CLASS_EXTRA_IDENTIFIER] !== ''
            && !empty($package->getExtra()['label']);
    }

    private function getPluginNameFromPackage(CompletePackageInterface $pluginPackage): string
    {
        return $pluginPackage->getExtra()[self::SHOPWARE_PLUGIN_CLASS_EXTRA_IDENTIFIER];
    }

    /**
     * @param array<string, PluginFromFileSystemStruct> $plugins
     *
     * @return array<string, PluginFromFileSystemStruct>
     */
    private function enrichWithVendorPlugins(
        array $plugins,
        string $projectDir,
        IOInterface $composerIO,
        ExceptionCollection $errors
    ): array {
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

            $localPlugin = $plugins[$pluginBaseClass] ?? null;

            $plugins[$pluginBaseClass] = (new PluginFromFileSystemStruct())->assign([
                'baseClass' => $pluginBaseClass,
                // use local path if it is also installed as a local plugin,
                // to allow updates over the store for composer managed plugins
                'path' => $localPlugin?->getPath() ?? $pluginPath,
                'managedByComposer' => true,
                // use local composer package (if it exists) as composer caches the version info
                'composerPackage' => $localPlugin?->getComposerPackage() ?? $composerPackage,
            ]);
        }

        $root = $composer->getPackage();
        if ($this->isShopwarePluginType($root) && $this->isPluginComposerValid($root)) {
            $pluginBaseClass = $this->getPluginNameFromPackage($root);
            $plugins[$pluginBaseClass] = (new PluginFromFileSystemStruct())->assign([
                'baseClass' => $pluginBaseClass,
                'path' => '.',
                'managedByComposer' => true,
                'composerPackage' => $root,
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
                    'Plugin composer.json has invalid "type" (must be "%s"), or invalid "extra/%s" value, or missing extra.label property',
                    self::COMPOSER_TYPE,
                    self::SHOPWARE_PLUGIN_CLASS_EXTRA_IDENTIFIER
                ),
            ]
        ));
    }
}
