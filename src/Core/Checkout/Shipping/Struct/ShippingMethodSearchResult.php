<?php declare(strict_types=1);

namespace Shopware\Checkout\Shipping\Struct;

use Shopware\Checkout\Shipping\Collection\ShippingMethodBasicCollection;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;

class ShippingMethodSearchResult extends ShippingMethodBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
