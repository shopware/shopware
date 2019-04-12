<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Context;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\PluginInterface;

class UninstallContext extends InstallContext
{
    /**
     * @var bool
     */
    private $keepUserData;

    public function __construct(
        PluginInterface $plugin,
        Context $context,
        string $currentShopwareVersion,
        string $currentPluginVersion,
        bool $keepUserData
    ) {
        parent::__construct($plugin, $context, $currentShopwareVersion, $currentPluginVersion);
        $this->keepUserData = $keepUserData;
    }

    public function keepUserData(): bool
    {
        return $this->keepUserData;
    }
}
