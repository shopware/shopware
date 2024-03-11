<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\Services;

use Shopware\Core\Framework\App\ShopId\ShopIdProvider as AppSystemShopIdProvider;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[Package('data-services')]
class ShopIdProvider
{
    public function __construct(
        private readonly AppSystemShopIdProvider $shopIdProvider,
        private readonly SystemConfigService $systemConfigService
    ) {
    }

    public function getShopId(): string
    {
        $shopId = $this->systemConfigService->get(AppSystemShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY);

        if (!\is_array($shopId)) {
            return $this->shopIdProvider->getShopId();
        }

        return $shopId['value'];
    }
}
