<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
class InstanceService
{
    public function __construct(
        private readonly string $shopwareVersion,
        private readonly ?string $instanceId
    ) {
    }

    public function getShopwareVersion(): string
    {
        if (str_ends_with($this->shopwareVersion, '-dev')) {
            return '___VERSION___';
        }

        return $this->shopwareVersion;
    }

    public function getInstanceId(): ?string
    {
        return $this->instanceId;
    }
}
