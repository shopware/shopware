<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use Shopware\Core\Kernel;

/**
 * @package merchant-services
 *
 * @deprecated tag:v6.5.0 - reason:becomes-internal
 */
class InstanceService
{
    private string $shopwareVersion;

    private ?string $instanceId;

    /**
     * @internal
     */
    public function __construct(string $shopwareVersion, ?string $instanceId)
    {
        $this->shopwareVersion = $shopwareVersion;
        $this->instanceId = $instanceId;
    }

    public function getShopwareVersion(): string
    {
        if ($this->shopwareVersion === Kernel::SHOPWARE_FALLBACK_VERSION) {
            return '___VERSION___';
        }

        return $this->shopwareVersion;
    }

    public function getInstanceId(): ?string
    {
        return $this->instanceId;
    }
}
