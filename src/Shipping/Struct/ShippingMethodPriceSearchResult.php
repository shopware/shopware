<?php declare(strict_types=1);

namespace Shopware\Shipping\Struct;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\Shipping\Collection\ShippingMethodPriceBasicCollection;

class ShippingMethodPriceSearchResult extends ShippingMethodPriceBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
