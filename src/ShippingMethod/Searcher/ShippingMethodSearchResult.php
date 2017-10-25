<?php declare(strict_types=1);

namespace Shopware\ShippingMethod\Searcher;

use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;
use Shopware\ShippingMethod\Struct\ShippingMethodBasicCollection;

class ShippingMethodSearchResult extends ShippingMethodBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
