<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Routing\Router;

use Shopware\Core\Content\ContentSystem\Routing\Entity\ContentRouteCollection;
use Shopware\Core\Content\ContentSystem\Routing\Entity\ContentRouteEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
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
     * @internal
     *
     * @param EntityRepository<ContentRouteCollection> $contentRouteRepository
     */
    public function __construct(
        protected readonly EntityRepository $contentRouteRepository
    ) {
    }

    public function build(SalesChannelContext $context): RouteCollection
    {
        $salesChannelId = $context->getSalesChannel()->getId();
        $collection = new RouteCollection();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addAssociation('salesChannels');
        $criteria->addSorting(new FieldSorting('priority', FieldSorting::DESCENDING));

        $routes = $this->contentRouteRepository->search($criteria, $context->getContext());

        /** @var ContentRouteEntity $contentRoute */
        foreach ($routes as $contentRoute) {
            $salesChannels = $contentRoute->getSalesChannels();

            if ($salesChannels === null || $salesChannels->count() === 0 || $salesChannels->has($salesChannelId)) {
                $route = new Route($contentRoute->getUrlPattern());
                $route->setDefault('_content_route_id', $contentRoute->getId());
                $route->setDefault('_content_route', $contentRoute);

                $collection->add('content_route_' . $contentRoute->getId(), $route);
            }
        }

        return $collection;
    }
}
