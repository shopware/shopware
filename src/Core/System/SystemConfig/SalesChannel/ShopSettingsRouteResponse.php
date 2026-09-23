<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ShopSettings>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ShopSettingsRouteResponse extends StoreApiResponse
{
    public function getSettings(): ShopSettings
    {
        return $this->object;
    }
}
