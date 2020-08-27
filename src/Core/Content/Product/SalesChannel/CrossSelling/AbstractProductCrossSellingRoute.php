<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\CrossSelling;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * This route will be used to load all cross selling lists of the provided product id
 */
abstract class AbstractProductCrossSellingRoute
{
    abstract public function getDecorated(): AbstractProductCrossSellingRoute;

    abstract public function load(string $productId, SalesChannelContext $context): ProductCrossSellingRouteResponse;
}
