<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Context;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Plugin;

#[Package('core')]
class UpdateContext extends InstallContext
{
    public function __construct(
        Plugin $plugin,
        Context $context,
        string $currentShopwareVersion,
        string $currentPluginVersion,
        MigrationCollection $migrationCollection,
        private readonly string $updatePluginVersion
    ) {
        parent::__construct($plugin, $context, $currentShopwareVersion, $currentPluginVersion, $migrationCollection);
    }

    public function getUpdatePluginVersion(): string
    {
        return $this->updatePluginVersion;
    }
}
