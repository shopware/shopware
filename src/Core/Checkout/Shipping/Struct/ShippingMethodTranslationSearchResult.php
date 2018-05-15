<?php declare(strict_types=1);

namespace Shopware\Checkout\Shipping\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Checkout\Shipping\Collection\ShippingMethodTranslationBasicCollection;

class ShippingMethodTranslationSearchResult extends ShippingMethodTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
