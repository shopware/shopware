<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use Shopware\Core\Kernel;

/**
 * @package merchant-services
 *
 * @internal
 */
class InstanceService
{
    public function __construct(private readonly string $shopwareVersion, private readonly ?string $instanceId)
    {
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
