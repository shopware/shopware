<?php declare(strict_types=1);

namespace Shopware\ShippingMethod\Searcher;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\ShippingMethod\Struct\ShippingMethodBasicCollection;

class ShippingMethodSearchResult extends ShippingMethodBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
