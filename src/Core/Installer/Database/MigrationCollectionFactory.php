<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Database;

use Doctrine\DBAL\Connection;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationRuntime;
use Shopware\Core\Framework\Migration\MigrationSource;

/**
 * @internal
 */
class MigrationCollectionFactory
{
    private string $projectDir;

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
    }

    public function getMigrationCollectionLoader(Connection $connection): MigrationCollectionLoader
    {
        return new MigrationCollectionLoader(
            $connection,
            new MigrationRuntime($connection, new NullLogger()),
            $this->collect()
        );
    }

    /**
     * @return list<MigrationSource>
     */
    private function collect(): array
    {
        return [
            new MigrationSource('core', []),
            self::createMigrationSource('V6_3', true),
            self::createMigrationSource('V6_4', true),
            self::createMigrationSource('V6_5'),
        ];
    }

    private function createMigrationSource(string $version, bool $addReplacements = false): MigrationSource
    {
        if (file_exists($this->projectDir . '/platform/src/Core/schema.sql')) {
            $coreBasePath = $this->projectDir . '/platform/src/Core';
            $storefrontBasePath = $this->projectDir . '/platform/src/Storefront';
            $adminBasePath = $this->projectDir . '/platform/src/Administration';
        } elseif (file_exists($this->projectDir . '/src/Core/schema.sql')) {
            $coreBasePath = $this->projectDir . '/src/Core';
            $storefrontBasePath = $this->projectDir . '/src/Storefront';
            $adminBasePath = $this->projectDir . '/src/Administration';
        } else {
            $coreBasePath = $this->projectDir . '/vendor/shopware/core';
            $storefrontBasePath = $this->projectDir . '/vendor/shopware/storefront';
            $adminBasePath = $this->projectDir . '/vendor/shopware/administration';
        }

        $hasStorefrontMigrations = is_dir($storefrontBasePath);
        $hasAdminMigrations = is_dir($adminBasePath);

        $source = new MigrationSource('core.' . $version, [
            sprintf('%s/Migration/%s', $coreBasePath, $version) => sprintf('Shopware\\Core\\Migration\\%s', $version),
        ]);

        if ($hasStorefrontMigrations) {
            $source->addDirectory(sprintf('%s/Migration/%s', $storefrontBasePath, $version), sprintf('Shopware\\Storefront\\Migration\\%s', $version));
        }

        if ($hasAdminMigrations) {
            $source->addDirectory(sprintf('%s/Migration/%s', $adminBasePath, $version), sprintf('Shopware\\Administration\\Migration\\%s', $version));
        }

        if ($addReplacements) {
            $source->addReplacementPattern(sprintf('#^(Shopware\\\\Core\\\\Migration\\\\)%s\\\\([^\\\\]*)$#', $version), '$1$2');
            if ($hasStorefrontMigrations) {
                $source->addReplacementPattern(sprintf('#^(Shopware\\\\Storefront\\\\Migration\\\\)%s\\\\([^\\\\]*)$#', $version), '$1$2');
            }
            if ($hasAdminMigrations) {
                $source->addReplacementPattern(sprintf('#^(Shopware\\\\Administration\\\\Migration\\\\)%s\\\\([^\\\\]*)$#', $version), '$1$2');
            }
        }

        return $source;
    }
}
