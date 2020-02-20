<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Context;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Plugin;

class UninstallContext extends InstallContext
{
    /**
     * @var bool
     */
    private $keepUserData;

    /**
     * @var bool
     */
    private $keepMigrations;

    public function __construct(
        Plugin $plugin,
        Context $context,
        string $currentShopwareVersion,
        string $currentPluginVersion,
        MigrationCollection $migrationCollection,
        bool $keepUserData,
        bool $keepMigrations = false
    ) {
        parent::__construct($plugin, $context, $currentShopwareVersion, $currentPluginVersion, $migrationCollection);
        $this->keepUserData = $keepUserData;
        $this->keepMigrations = $keepMigrations;
    }

    public function keepUserData(): bool
    {
        return $this->keepUserData;
    }

    /**
     * By default the executed migrations for plugins are deleted during uninstall.
     *
     * Call `enableKeepMigrations` to opt-out from the deletion
     *
     * The default will change to true in v6.3.0
     */
    public function keepMigrations(): bool
    {
        return $this->keepMigrations;
    }

    /**
     * This will be the default in v6.3.0
     */
    public function enableKeepMigrations(): void
    {
        $this->keepMigrations = true;
    }
}
