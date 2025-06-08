<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<CurrencyCollection>
 */
#[Package('fundamentals@framework')]
class CurrencyRouteResponse extends StoreApiResponse
{
    public function getCurrencies(): CurrencyCollection
    {
        return $this->object;
    }
}
