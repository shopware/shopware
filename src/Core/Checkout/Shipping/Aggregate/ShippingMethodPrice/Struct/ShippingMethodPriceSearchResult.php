<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\Struct;

use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\Collection\ShippingMethodPriceBasicCollection;
use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;

class ShippingMethodPriceSearchResult extends ShippingMethodPriceBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
