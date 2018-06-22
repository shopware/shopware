<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Context;

use Shopware\Core\Framework\Plugin;

class UninstallContext extends InstallContext
{
    /**
     * @var bool
     */
    private $keepUserData;

    /**
     * @param Plugin $plugin
     * @param string $shopwareVersion
     * @param string $currentVersion
     * @param bool   $keepUserData
     */
    public function __construct(
        Plugin $plugin,
        $shopwareVersion,
        $currentVersion,
        $keepUserData
    ) {
        parent::__construct($plugin, $shopwareVersion, $currentVersion);
        $this->keepUserData = $keepUserData;
    }

    /**
     * @return bool
     */
    public function keepUserData(): bool
    {
        return $this->keepUserData;
    }
}
