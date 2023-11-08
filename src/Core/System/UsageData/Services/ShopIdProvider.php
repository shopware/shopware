<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\Services;

use Shopware\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('merchant-services')]
class ShopIdProvider
{
    final public const USAGE_DATA_SHOP_ID_CONFIG_KEY = 'usageData-shopId';

    public function __construct(
        private readonly AbstractKeyValueStorage $config,
    ) {
    }

    public function getShopId(): string
    {
        if (!$this->config->has(self::USAGE_DATA_SHOP_ID_CONFIG_KEY)) {
            $this->config->set(self::USAGE_DATA_SHOP_ID_CONFIG_KEY, Uuid::randomHex());
        }

        return (string) $this->config->get(self::USAGE_DATA_SHOP_ID_CONFIG_KEY);
    }
}
