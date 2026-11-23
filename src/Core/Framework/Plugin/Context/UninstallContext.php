<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Context;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Plugin;

#[Package('framework')]
class UninstallContext extends InstallContext
{
    public function __construct(
        Plugin $plugin,
        Context $context,
        string $currentShopwareVersion,
        string $currentPluginVersion,
        MigrationCollection $migrationCollection,
        private readonly bool $keepUserData
    ) {
        parent::__construct($plugin, $context, $currentShopwareVersion, $currentPluginVersion, $migrationCollection);
    }

    /**
     * If false is returned, the plugin should remove its data (e.g. its tables) in `uninstall()`.
     * Shopware then also removes the plugin's entries in the `migration` table, its system config,
     * custom entities and custom fields, and its theme.
     */
    public function keepUserData(): bool
    {
        return $this->keepUserData;
    }
}
