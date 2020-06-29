<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route can be used to load all seo urls of the authenticated sales channel.
 * With this route it is also possible to send the standard API parameters such as: 'page', 'limit', 'filter', etc.
 */
abstract class AbstractSeoUrlRoute
{
    abstract public function getDecorated(): AbstractSeoUrlRoute;

    /**
     * @deprecated tag:v6.4.0 - Parameter $criteria will be mandatory in future implementation
     */
    abstract public function load(Request $request, SalesChannelContext $context/*, Criteria $criteria*/): SeoUrlRouteResponse;
}
