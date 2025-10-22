<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Routing\Router;

use Shopware\Core\Content\ContentSystem\Routing\Entity\ContentRouteCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * @final
 */
#[Package('discovery')]
class RouteCollectionBuilder
{
    /**
     * Parameter name for storing the matched ContentRouteEntity in route defaults
     */
    public const PARAM_CONTENT_ROUTE = '_content_route';

    /**
     * Parameter name for storing the matched ContentRouteEntity ID in route defaults
     */
    public const PARAM_CONTENT_ROUTE_ID = '_content_route_id';

    /**
     * Symfony's standard route name parameter
     */
    public const PARAM_ROUTE = '_route';

    /**
     * @internal
     *
     * @param EntityRepository<ContentRouteCollection> $contentRouteRepository
     */
    public function __construct(
        private readonly EntityRepository $contentRouteRepository
    ) {
    }

    public function build(SalesChannelContext $context): RouteCollection
    {
        $criteria = new Criteria();

        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new OrFilter([
            new EqualsFilter('layoutAssignments.salesChannelId', $context->getSalesChannel()->getId()),
            new EqualsFilter('layoutAssignments.salesChannelId', null),
        ]));

        $criteria->addSorting(new FieldSorting('priority', FieldSorting::DESCENDING));

        $routes = $this->contentRouteRepository->search($criteria, $context->getContext());

        $collection = new RouteCollection();

        foreach ($routes as $contentRoute) {
            $route = new Route($contentRoute->getUrlPattern());
            $route->setDefault(self::PARAM_CONTENT_ROUTE_ID, $contentRoute->getId());
            $route->setDefault(self::PARAM_CONTENT_ROUTE, $contentRoute);

            $collection->add('content_route_' . $contentRoute->getId(), $route);
        }

        return $collection;
    }
}
