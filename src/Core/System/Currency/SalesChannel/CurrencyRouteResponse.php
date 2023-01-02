<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

#[Package('inventory')]
class CurrencyRouteResponse extends StoreApiResponse
{
    /**
     * @var CurrencyCollection
     */
    protected $object;

    public function __construct(CurrencyCollection $currencies)
    {
        parent::__construct($currencies);
    }

    public function getCurrencies(): CurrencyCollection
    {
        return $this->object;
    }
}
