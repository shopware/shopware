<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Context;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin;

class UpdateContext extends InstallContext
{
    /**
     * @var string
     */
    private $updateVersion;

    public function __construct(
        Plugin $plugin,
        Context $context,
        string $shopwareVersion,
        string $currentVersion,
        string $updateVersion
    ) {
        parent::__construct($plugin, $context, $shopwareVersion, $currentVersion);
        $this->updateVersion = $updateVersion;
    }

    public function getUpdateVersion(): string
    {
        return $this->updateVersion;
    }
}
