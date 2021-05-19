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
     * @Entity("product")
     * @OA\Post(
     *      path="/search",
     *      summary="Search for products",
     *      description="Performs a search for products which can be used to display a product listing.",
     *      operationId="searchPage",
     *      tags={"Store API","Product"},
     *      @OA\RequestBody(
     *          @OA\JsonContent(
     *              type="object",
     *              allOf={
     *                  @OA\Schema(ref="#/components/schemas/ProductListingCriteria"),
     *                  @OA\Schema(ref="#/components/schemas/ProductListingFlags"),
     *                  @OA\Schema(type="object",
     *                      required={
     *                          "search"
     *                      },
     *                      @OA\Property(
     *                          property="search",
     *                          description="Using the search parameter, the server performs a text search on all records based on their data model and weighting as defined in the entity definition using the SearchRanking flag.",
     *                          type="string"
     *                      )
     *                  )
     *              }
     *          )
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="Returns a product listing containing all products and additional fields to display a listing.",
     *          @OA\JsonContent(ref="#/components/schemas/ProductListingResult")
     *     )
     * )
     * @Route("/store-api/search", name="store-api.search", methods={"POST"})
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
