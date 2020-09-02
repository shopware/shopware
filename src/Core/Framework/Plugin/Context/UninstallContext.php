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
     * @deprecated tag:v6.4.0 - Will be removed. Use $keepUserData instead
     *
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
        /* @deprecated tag:v6.4.0 - Will be removed. Set $keepUserData instead*/
        bool $keepMigrations = true
    ) {
        parent::__construct($plugin, $context, $currentShopwareVersion, $currentPluginVersion, $migrationCollection);
        $this->keepUserData = $keepUserData;
        $this->keepMigrations = $keepMigrations;
    }

    /**
     * If true is returned, migrations of the plugin will also be removed
     */
    public function keepUserData(): bool
    {
        return $this->keepUserData;
    }

    /**
     * @deprecated tag:v6.4.0 - Will be removed, use keepUserData() instead.
     * Starting with v6.4.0, migrations will be removed if keepUserData() returns false.
     */
    public function keepMigrations(): bool
    {
        return $this->keepMigrations;
    }

    /**
     * @deprecated tag:v6.4.0  - Will be removed. If migrations should be removed or not, is handled by the keepUserData parameter
     */
    public function enableKeepMigrations(): void
    {
        trigger_error('Do not use enableKeepMigrations() anymore, it will be removed in v6.4.0. See UPGRADE-6.3.md for further information.', E_USER_DEPRECATED);
        $this->keepMigrations = true;
    }
}
