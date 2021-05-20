<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing;

use OpenApi\Annotations as OA;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
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
class ResolveCriteriaProductListingRoute extends AbstractProductListingRoute
{
    private AbstractProductListingRoute $decorated;

    private EventDispatcherInterface $eventDispatcher;

    public function __construct(AbstractProductListingRoute $decorated, EventDispatcherInterface $eventDispatcher)
    {
        $this->decorated = $decorated;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function getDecorated(): AbstractProductListingRoute
    {
        return $this->decorated;
    }

    /**
     * @Since("6.2.0.0")
     * @Entity("product")
     * @OA\Post(
     *      path="/product-listing/{categoryId}",
     *      summary="Fetch a product listing by category",
     *      description="Fetches a product listing for a specific category. It also provides filters, sortings and property aggregations, analogous to the /search endpoint.",
     *      operationId="readProductListing",
     *      tags={"Store API","Product"},
     *      @OA\RequestBody(
     *          @OA\JsonContent(
     *                  type="object",
     *                  allOf={
     *                      @OA\Schema(ref="#/components/schemas/ProductListingCriteria"),
     *                      @OA\Schema(ref="#/components/schemas/ProductListingFlags")
     *                  }
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="categoryId",
     *          description="Identifier of a category.",
     *          @OA\Schema(type="string"),
     *          in="path",
     *          required=true
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="Returns a product listing containing all products and additional fields to display a listing.",
     *          @OA\JsonContent(ref="#/components/schemas/ProductListingResult")
     *     )
     * )
     * @Route("/store-api/product-listing/{categoryId}", name="store-api.product.listing", methods={"POST"})
     */
    public function load(string $categoryId, Request $request, SalesChannelContext $context, Criteria $criteria): ProductListingRouteResponse
    {
        $this->eventDispatcher->dispatch(
            new ProductListingCriteriaEvent($request, $criteria, $context)
        );

        return $this->getDecorated()->load($categoryId, $request, $context, $criteria);
    }
}
