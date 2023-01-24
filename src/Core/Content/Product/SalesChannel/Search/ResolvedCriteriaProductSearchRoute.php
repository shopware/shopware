<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Search;

use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @package system-settings
 */
#[Route(defaults: ['_routeScope' => ['store-api']])]
class ResolvedCriteriaProductSearchRoute extends AbstractProductSearchRoute
{
    /**
     * @internal
     */
    public function __construct(private readonly AbstractProductSearchRoute $decorated, private readonly EventDispatcherInterface $eventDispatcher, private readonly DefinitionInstanceRegistry $registry, private readonly RequestCriteriaBuilder $criteriaBuilder)
    {
    }

    public function getDecorated(): AbstractProductSearchRoute
    {
        return $this->decorated;
    }

    /**
     * @Since("6.2.0.0")
     */
    #[Route(path: '/store-api/search', name: 'store-api.search', methods: ['POST'], defaults: ['_entity' => 'product'])]
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): ProductSearchRouteResponse
    {
        $criteria = $this->criteriaBuilder->handleRequest(
            $request,
            $criteria,
            $this->registry->getByEntityName('product'),
            $context->getContext()
        );

        $this->eventDispatcher->dispatch(
            new ProductSearchCriteriaEvent($request, $criteria, $context),
            ProductEvents::PRODUCT_SEARCH_CRITERIA
        );

        return $this->getDecorated()->load($request, $context, $criteria);
    }
}
