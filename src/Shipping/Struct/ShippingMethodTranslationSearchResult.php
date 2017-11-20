<?php declare(strict_types=1);

namespace Shopware\Shipping\Struct;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\Shipping\Collection\ShippingMethodTranslationBasicCollection;

class ShippingMethodTranslationSearchResult extends ShippingMethodTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
