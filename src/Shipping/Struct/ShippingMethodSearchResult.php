<?php declare(strict_types=1);

namespace Shopware\Shipping\Struct;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\Shipping\Collection\ShippingMethodBasicCollection;

class ShippingMethodSearchResult extends ShippingMethodBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
