<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Search;

use OpenApi\Annotations as OA;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @RouteScope(scopes={"store-api"})
 */
class ResolvedCriteriaProductSearchRoute extends AbstractProductSearchRoute
{
    private AbstractProductSearchRoute $decorated;

    private EventDispatcherInterface $eventDispatcher;

    private DefinitionInstanceRegistry $registry;

    private RequestCriteriaBuilder $criteriaBuilder;

    public function __construct(AbstractProductSearchRoute $decorated, EventDispatcherInterface $eventDispatcher, DefinitionInstanceRegistry $registry, RequestCriteriaBuilder $criteriaBuilder)
    {
        $this->decorated = $decorated;
        $this->eventDispatcher = $eventDispatcher;
        $this->registry = $registry;
        $this->criteriaBuilder = $criteriaBuilder;
    }

    public function getDecorated(): AbstractProductSearchRoute
    {
        return $this->decorated;
    }

    /**
     * @Since("6.2.0.0")
     * @OA\Get(
     *      path="/search",
     *      summary="Search",
     *      operationId="searchPage",
     *      tags={"Store API","Search"},
     *      @OA\Parameter(
     *          name="search",
     *          description="Search term",
     *          in="query",
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="Found products",
     *          @OA\JsonContent(ref="#/components/schemas/ProductListingResult")
     *     )
     * )
     * @Route("/store-api/search", name="store-api.search", methods={"POST"})
     * @Entity("product")
     */
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
