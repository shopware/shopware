<?php declare(strict_types=1);

namespace Shopware\Checkout\Shipping\Aggregate\ShippingMethodPrice\Struct;

use Shopware\Checkout\Shipping\Aggregate\ShippingMethodPrice\Collection\ShippingMethodPriceBasicCollection;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;

class ShippingMethodPriceSearchResult extends ShippingMethodPriceBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
