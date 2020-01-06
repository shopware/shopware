<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Context;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Plugin;

class InstallContext
{
    /**
     * @var Plugin
     */
    private $plugin;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var string
     */
    private $currentShopwareVersion;

    /**
     * @var string
     */
    private $currentPluginVersion;

    /**
     * @var MigrationCollection
     */
    private $migrationCollection;

    private $autoMigrate = true;

    public function __construct(
        Plugin $plugin,
        Context $context,
        string $currentShopwareVersion,
        string $currentPluginVersion,
        MigrationCollection $migrationCollection
    ) {
        $this->plugin = $plugin;
        $this->context = $context;
        $this->currentShopwareVersion = $currentShopwareVersion;
        $this->currentPluginVersion = $currentPluginVersion;
        $this->migrationCollection = $migrationCollection;
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
