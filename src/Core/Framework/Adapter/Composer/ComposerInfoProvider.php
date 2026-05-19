<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Composer;

use Composer\InstalledVersions;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class ComposerInfoProvider
{
    /**
     * @var list<ComposerPackage>|null
     */
    private static ?array $fakedPackages = null;

    /**
     * @return list<ComposerPackage>
     */
    public static function getComposerPackages(string $type): array
    {
        // For testing purposes, we can fake the packages
        if (self::$fakedPackages !== null) {
            return self::$fakedPackages;
        }

        $rawPackages = InstalledVersions::getAllRawData();
        $packages = [];

        foreach ($rawPackages as $rootPackage) {
            if (($rootPackage['root']['type'] ?? '') === $type) {
                $packages[$rootPackage['root']['name']] = new ComposerPackage(
                    name: $rootPackage['root']['name'],
                    version: $rootPackage['root']['version'] ?? '1.0.0',
                    prettyVersion: $rootPackage['root']['pretty_version'] ?? $rootPackage['root']['version'] ?? '1.0.0.0',
                    path: $rootPackage['root']['install_path'] ?? '',
                );
            }

            foreach ($rootPackage['versions'] ?? [] as $packageName => $packageData) {
                if (($packageData['type'] ?? '') !== $type) {
                    continue;
                }

                $packages[$packageName] = new ComposerPackage(
                    name: $packageName,
                    version: $packageData['version'] ?? '1.0.0',
                    prettyVersion: $packageData['pretty_version'] ?? $packageData['version'] ?? '1.0.0.0',
                    path: $packageData['install_path'] ?? '',
                );
            }
        }

        return array_values($packages);
    }

    /**
     * @param list<ComposerPackage> $packages
     */
    public static function fake(array $packages): void
    {
        self::$fakedPackages = $packages;
    }

    public static function reset(): void
    {
        self::$fakedPackages = null;
    }
}
