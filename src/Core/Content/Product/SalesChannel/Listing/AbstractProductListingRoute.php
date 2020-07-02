<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route is used for the product listing in the cms pages
 */
abstract class AbstractProductListingRoute
{
    abstract public function getDecorated(): AbstractProductListingRoute;

    /**
     * @deprecated tag:v6.4.0 - Parameter $criteria will be mandatory in future implementation
     */
    abstract public function load(string $categoryId, Request $request, SalesChannelContext $salesChannelContext/*, Criteria $criteria*/): ProductListingRouteResponse;
}
