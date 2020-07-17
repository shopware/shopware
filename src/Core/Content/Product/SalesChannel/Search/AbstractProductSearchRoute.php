<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Search;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route is used for the product search in the search pages
 */
abstract class AbstractProductSearchRoute
{
    abstract public function getDecorated(): AbstractProductSearchRoute;

    /**
     * @deprecated tag:v6.4.0 - Parameter $criteria will be mandatory in future implementation
     */
    abstract public function load(Request $request, SalesChannelContext $context/*, Criteria $criteria*/): ProductSearchRouteResponse;
}
