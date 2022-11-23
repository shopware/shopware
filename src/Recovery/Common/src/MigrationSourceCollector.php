<?php declare(strict_types=1);

namespace Shopware\Recovery\Common;

use Shopware\Core\Framework\Migration\MigrationSource as CoreMigrationSource;
use function file_exists;
use function is_dir;
use function sprintf;
use const SW_PATH;

class MigrationSourceCollector
{
    public static function collect(): array
    {
        return [
            new CoreMigrationSource('core', []),
            self::createMigrationSource('V6_3'),
            self::createMigrationSource('V6_4'),
            self::createMigrationSource('V6_5'),
            self::createMigrationSource('V6_6'),
        ];
    }

    private static function createMigrationSource(string $version): CoreMigrationSource
    {
        if (file_exists(SW_PATH . '/platform/src/Core/schema.sql')) {
            $coreBasePath = SW_PATH . '/platform/src/Core';
            $storefrontBasePath = SW_PATH . '/platform/src/Storefront';
            $adminBasePath = SW_PATH . '/platform/src/Administration';
        } elseif (file_exists(SW_PATH . '/src/Core/schema.sql')) {
            $coreBasePath = SW_PATH . '/src/Core';
            $storefrontBasePath = SW_PATH . '/src/Storefront';
            $adminBasePath = SW_PATH . '/src/Administration';
        } else {
            $coreBasePath = SW_PATH . '/vendor/shopware/core';
            $storefrontBasePath = SW_PATH . '/vendor/shopware/storefront';
            $adminBasePath = SW_PATH . '/vendor/shopware/administration';
        }

        $hasStorefrontMigrations = is_dir($storefrontBasePath);
        $hasAdminMigrations = is_dir($adminBasePath);

        $source = new CoreMigrationSource('core.' . $version, [
            sprintf('%s/Migration/%s', $coreBasePath, $version) => sprintf('Shopware\\Core\\Migration\\%s', $version),
        ]);

        if ($hasStorefrontMigrations) {
            $source->addDirectory(sprintf('%s/Migration/%s', $storefrontBasePath, $version), sprintf('Shopware\\Storefront\\Migration\\%s', $version));
        }

        if ($hasAdminMigrations) {
            $source->addDirectory(sprintf('%s/Migration/%s', $adminBasePath, $version), sprintf('Shopware\\Administration\\Migration\\%s', $version));
        }

        return $source;
    }
}
