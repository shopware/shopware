<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Context;

use Shopware\Core\Framework\Plugin\Plugin;

class UpdateContext extends InstallContext
{
    /**
     * @var string
     */
    private $updateVersion;

    /**
     * @param Plugin $plugin
     * @param string $shopwareVersion
     * @param string $currentVersion
     * @param string $updateVersion
     */
    public function __construct(
        Plugin $plugin,
        $shopwareVersion,
        $currentVersion,
        $updateVersion
    ) {
        parent::__construct($plugin, $shopwareVersion, $currentVersion);
        $this->updateVersion = $updateVersion;
    }

    /**
     * @return string
     */
    public function getUpdateVersion()
    {
        return $this->updateVersion;
    }
}
