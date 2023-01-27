<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Context;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Plugin;

#[Package('core')]
class InstallContext
{
    private bool $autoMigrate = true;

    public function __construct(
        private readonly Plugin $plugin,
        private readonly Context $context,
        private readonly string $currentShopwareVersion,
        private readonly string $currentPluginVersion,
        private readonly MigrationCollection $migrationCollection
    ) {
    }

    public function getPlugin(): Plugin
    {
        return $this->plugin;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getCurrentShopwareVersion(): string
    {
        return $this->currentShopwareVersion;
    }

    public function getCurrentPluginVersion(): string
    {
        return $this->currentPluginVersion;
    }

    public function getMigrationCollection(): MigrationCollection
    {
        return $this->migrationCollection;
    }

    public function isAutoMigrate(): bool
    {
        return $this->autoMigrate;
    }

    public function setAutoMigrate(bool $autoMigrate): void
    {
        $this->autoMigrate = $autoMigrate;
    }
}
