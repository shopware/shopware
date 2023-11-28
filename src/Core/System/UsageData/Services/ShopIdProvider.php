<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\Services;

use Shopware\Core\Framework\App\Exception\AppUrlChangeDetectedException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('data-services')]
class ShopIdProvider
{
    public function __construct(
        private readonly \Shopware\Core\Framework\App\ShopId\ShopIdProvider $shopIdProvider,
    ) {
    }

    public function getShopId(): string
    {
        try {
            return $this->shopIdProvider->getShopId();
        } catch (AppUrlChangeDetectedException $e) {
            return $e->getShopId();
        }
    }
}
