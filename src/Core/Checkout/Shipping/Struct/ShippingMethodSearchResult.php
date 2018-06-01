<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Struct;

use Shopware\Core\Checkout\Shipping\Collection\ShippingMethodBasicCollection;
use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;

class ShippingMethodSearchResult extends ShippingMethodBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
