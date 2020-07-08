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
     * @deprecated tag:v6.4.0
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
        /* @deprecated tag:v6.4.0 */
        bool $keepMigrations = true
    ) {
        parent::__construct($plugin, $context, $currentShopwareVersion, $currentPluginVersion, $migrationCollection);
        $this->keepUserData = $keepUserData;
        $this->keepMigrations = $keepMigrations;
        if (func_num_args() === 7) {
            trigger_error('Do not supply $keepMigrations anymore, it will be removed in v6.4.0. See UPGRADE-6.3.md for further information.', E_USER_DEPRECATED);
        }
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
     * @deprecated tag:v6.4.0 use keepUserData() instead. Starting with v6.4.0 migrations will always be removed if
     * keepUserData() returns false.
     */
    public function keepMigrations(): bool
    {
        return $this->keepMigrations;
    }

    /**
     * @deprecated tag:v6.4.0
     */
    public function enableKeepMigrations(): void
    {
        trigger_error('Do not use enableKeepMigrations() anymore, it will be removed in v6.4.0. See UPGRADE-6.3.md for further information.', E_USER_DEPRECATED);
        $this->keepMigrations = true;
    }
}
