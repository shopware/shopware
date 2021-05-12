<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Search;

use OpenApi\Annotations as OA;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Events\ProductSearchResultEvent;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @RouteScope(scopes={"store-api"})
 */
class ProductSearchRoute extends AbstractProductSearchRoute
{
    private EventDispatcherInterface $eventDispatcher;

    private ProductSearchBuilderInterface $searchBuilder;

    private ProductListingLoader $productListingLoader;

    public function __construct(
        ProductSearchBuilderInterface $searchBuilder,
        EventDispatcherInterface $eventDispatcher,
        ProductListingLoader $productListingLoader
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->searchBuilder = $searchBuilder;
        $this->productListingLoader = $productListingLoader;
    }

    public function getDecorated(): AbstractProductSearchRoute
    {
        throw new DecorationPatternException(self::class);
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
     *          required=true,
     *          @OA\JsonContent(
     *              required={
     *                  "search"
     *              },
     *              @OA\Property(
     *                  property="search",
     *                  type="string",
     *                  description="Using the search parameter, the server performs a text search on all records based on their data model and weighting as defined in the entity definition using the SearchRanking flag."
     *              )
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
        if (!$request->get('search')) {
            throw new MissingRequestParameterException('search');
        }

        $context->getContext()->addState(Context::STATE_ELASTICSEARCH_AWARE);

        $criteria->addFilter(
            new ProductAvailableFilter($context->getSalesChannel()->getId(), ProductVisibilityDefinition::VISIBILITY_SEARCH)
        );

        $this->searchBuilder->build($request, $criteria, $context);

        $result = $this->productListingLoader->load($criteria, $context);

        $result = ProductListingResult::createFrom($result);

        $this->eventDispatcher->dispatch(
            new ProductSearchResultEvent($request, $result, $context),
            ProductEvents::PRODUCT_SEARCH_RESULT
        );

        $result->addCurrentFilter('search', $request->get('search'));

        return new ProductSearchRouteResponse($result);
    }
}
