<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTranslation\Struct;

use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTranslation\Collection\ShippingMethodTranslationBasicCollection;
use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;

class ShippingMethodTranslationSearchResult extends ShippingMethodTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
