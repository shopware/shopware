<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\KernelPluginLoader;

use Composer\InstalledVersions;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Util\PluginFinder;

/**
 * @phpstan-import-type PluginInfo from KernelPluginLoader
 */
#[Package('core')]
class ComposerPluginLoader extends KernelPluginLoader
{
    /**
     * @return array<PluginInfo>
     */
    public function fetchPluginInfos(): array
    {
        $this->loadPluginInfos();

        return $this->pluginInfos;
    }

    protected function loadPluginInfos(): void
    {
        $composerPlugins = InstalledVersions::getInstalledPackagesByType(PluginFinder::COMPOSER_TYPE);

        $this->pluginInfos = [];

        foreach ($composerPlugins as $composerName) {
            $path = InstalledVersions::getInstallPath($composerName);
            $composerJsonPath = $path . '/composer.json';

            if (!\file_exists($composerJsonPath)) {
                continue;
            }

            $composerJsonContent = \file_get_contents($composerJsonPath);
            \assert(\is_string($composerJsonContent));

            $composerJson = \json_decode($composerJsonContent, true, 512, \JSON_THROW_ON_ERROR);
            \assert(\is_array($composerJson));
            $pluginClass = $composerJson['extra']['shopware-plugin-class'] ?? '';

            if (\defined('\STDERR') && ($pluginClass === '' || !\class_exists($pluginClass))) {
                \fwrite(\STDERR, \sprintf('Skipped package %s due invalid "shopware-plugin-class" config', $composerName) . \PHP_EOL);

                continue;
            }

            $nameParts = \explode('\\', (string) $pluginClass);

            $this->pluginInfos[] = [
                'name' => \end($nameParts),
                'baseClass' => $pluginClass,
                'active' => true,
                'path' => $path ?? '',
                'version' => InstalledVersions::getPrettyVersion($composerName),
                'autoload' => $composerJson['autoload'] ?? [],
                'managedByComposer' => true,
                'composerName' => $composerName,
            ];
        }
    }
}
