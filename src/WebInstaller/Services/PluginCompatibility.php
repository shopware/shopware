<?php declare(strict_types=1);

namespace Shopware\WebInstaller\Services;

use Composer\Semver\Semver;
use Shopware\Core\Framework\Log\Package;

/**
 * Any plugins which  execute composer commands @see \Shopware\Commercial\SwagCommercial::executeComposerCommands
 * must be removed from the project `composer.json` file when their shopware dependency constraint is not valid with the updated Shopware version.
 *
 * Eg: Shopware is being updated to 6.6. Plugin `shopware/commercial` depends on `shopware/core` with a constraint like "^6.5".
 * It must be removed from the project in order for it to be updated, otherwise `composer update` will fail. The plugin will be added back
 * to the project's `composer.json` when it is updated again.
 *
 * @internal
 */
#[Package('core')]
class PluginCompatibility
{
    private const SHOPWARE_PACKAGES = [
        'shopware/core',
        'shopware/administration',
        'shopware/storefront',
        'shopware/elasticsearch',
    ];

    /**
     * @var array<array{name: string, require: array<string, string>}>
     */
    private array $installedPackages = [];

    public function __construct(
        private string $projectComposerJsonFile,
        private string $shopwareUpgradeVersion,
    ) {
        $installedJsonLocation = sprintf(
            '%s/vendor/composer/installed.json',
            \dirname((string) realpath($this->projectComposerJsonFile))
        );

        /**
         * array{packages: array<array{name: string, require: array<string, string>}>}
         */
        $installedJson = json_decode(
            (string) file_get_contents($installedJsonLocation),
            true,
            \JSON_THROW_ON_ERROR
        );

        $this->installedPackages = array_combine(
            array_column($installedJson['packages'], 'name'),
            $installedJson['packages']
        );
    }

    public function removeIncompatible(): void
    {
        $shopwarePlugins = $this->getInstalledPackagesByType('shopware-platform-plugin');

        // we only care about plugins directly in `custom/plugins` because plugins directly
        // managed by composer should be updated correctly, if a compatible version is available
        $customComposerPlugins = array_filter($shopwarePlugins, function (string $plugin) {
            $path = $this->getInstallPath($plugin);

            if ($path === null) {
                return false;
            }

            $pathParts = explode('/', $path);

            // we use a combination of `realpath` and path comparison to check that a plugin is installed into
            // custom/plugins because the plugin will be symlinked from custom/plugins to vendor.
            return ['custom', 'plugins'] === \array_slice($pathParts, -3, 2);
        });

        $nonCompatible = array_filter(
            $customComposerPlugins,
            fn ($plugin) => !$this->isPluginCompatible($plugin, $this->shopwareUpgradeVersion),
        );

        if (empty($nonCompatible)) {
            return;
        }

        /** @var array{require: array<string, string>} $composerJson */
        $composerJson = json_decode((string) file_get_contents($this->projectComposerJsonFile), true, \JSON_THROW_ON_ERROR);

        foreach ($nonCompatible as $plugin) {
            if (isset($composerJson['require'][$plugin])) {
                unset($composerJson['require'][$plugin]);
            }
        }

        file_put_contents($this->projectComposerJsonFile, json_encode($composerJson, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array<string>
     */
    private function getInstalledPackagesByType(string $type): array
    {
        $packagesByType = [];

        foreach ($this->installedPackages as $package) {
            if (isset($package['type']) && $package['type'] === $type) {
                $packagesByType[] = $package['name'];
            }
        }

        return $packagesByType;
    }

    private function getInstallPath(string $name): ?string
    {
        if (isset($this->installedPackages[$name]['install-path'])) {
            // install path is relative to installed.json
            $path = sprintf(
                '%s/vendor/composer/%s',
                \dirname($this->projectComposerJsonFile),
                $this->installedPackages[$name]['install-path']
            );

            return realpath($path) ?: null;
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function getRequires(string $name): array
    {
        return $this->installedPackages[$name]['require'] ?? [];
    }

    private function isPluginCompatible(string $plugin, string $shopwareVersion): bool
    {
        $pluginCompatible = true;

        foreach ($this->getRequires($plugin) as $packageDep => $constraint) {
            if (\in_array($packageDep, self::SHOPWARE_PACKAGES, true)) {
                $satisfies = Semver::satisfies($shopwareVersion, $constraint);

                if (!$satisfies) {
                    $pluginCompatible = false;
                }
            }
        }

        return $pluginCompatible;
    }
}
