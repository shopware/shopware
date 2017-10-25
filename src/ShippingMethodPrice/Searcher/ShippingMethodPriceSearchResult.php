<?php declare(strict_types=1);

namespace Shopware\ShippingMethodPrice\Searcher;

use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;
use Shopware\ShippingMethodPrice\Struct\ShippingMethodPriceBasicCollection;

class ShippingMethodPriceSearchResult extends ShippingMethodPriceBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
