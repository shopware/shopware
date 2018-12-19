<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Context;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin;

class UpdateContext extends InstallContext
{
    /**
     * @var string
     */
    private $updatePluginVersion;

    public function __construct(
        Plugin $plugin,
        Context $context,
        string $currentShopwareVersion,
        string $currentPluginVersion,
        string $updatePluginVersion
    ) {
        parent::__construct($plugin, $context, $currentShopwareVersion, $currentPluginVersion);
        $this->updatePluginVersion = $updatePluginVersion;
    }

    public function getUpdatePluginVersion(): string
    {
        return $this->updatePluginVersion;
    }
}
