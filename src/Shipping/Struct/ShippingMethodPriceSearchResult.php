<?php declare(strict_types=1);

namespace Shopware\Shipping\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Shipping\Collection\ShippingMethodPriceBasicCollection;

class ShippingMethodPriceSearchResult extends ShippingMethodPriceBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
