<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Suggest;

use OpenApi\Annotations as OA;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Events\ProductSuggestCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSuggestResultEvent;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @RouteScope(scopes={"store-api"})
 */
class ProductSuggestRoute extends AbstractProductSuggestRoute
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ProductSearchBuilderInterface
     */
    private $searchBuilder;

    /**
     * @var ProductListingLoader
     */
    private $productListingLoader;

    /**
     * @var ProductDefinition
     */
    private $definition;

    /**
     * @var RequestCriteriaBuilder
     */
    private $criteriaBuilder;

    public function __construct(
        ProductSearchBuilderInterface $searchBuilder,
        EventDispatcherInterface $eventDispatcher,
        ProductListingLoader $productListingLoader,
        ProductDefinition $definition,
        RequestCriteriaBuilder $criteriaBuilder
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->searchBuilder = $searchBuilder;
        $this->productListingLoader = $productListingLoader;
        $this->definition = $definition;
        $this->criteriaBuilder = $criteriaBuilder;
    }

    public function getDecorated(): AbstractProductSuggestRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Entity("product")
     * @OA\Get(
     *      path="/search-suggest",
     *      description="Search suggests",
     *      operationId="searchSuggest",
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
     *          @OA\JsonContent(ref="#/definitions/ProductListingResult")
     *     )
     * )
     * @Route("/store-api/v{version}/search-suggest", name="store-api.search.suggest", methods={"POST"})
     */
    public function load(Request $request, SalesChannelContext $context, ?Criteria $criteria = null): ProductSuggestRouteResponse
    {
        if (!$request->get('search')) {
            throw new MissingRequestParameterException('search');
        }

        // @deprecated tag:v6.4.0 - Criteria will be required
        if (!$criteria) {
            $criteria = new Criteria();
            $criteria->setLimit(10);
            $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
            $criteria = $this->criteriaBuilder->handleRequest($request, $criteria, $this->definition, $context->getContext());
        }

        $criteria->addFilter(
            new ProductAvailableFilter($context->getSalesChannel()->getId(), ProductVisibilityDefinition::VISIBILITY_SEARCH)
        );

        $this->searchBuilder->build($request, $criteria, $context);

        $this->eventDispatcher->dispatch(
            new ProductSuggestCriteriaEvent($request, $criteria, $context),
            ProductEvents::PRODUCT_SUGGEST_CRITERIA
        );

        $result = $this->productListingLoader->load($criteria, $context);

        $result = ProductListingResult::createFrom($result);

        $this->eventDispatcher->dispatch(
            new ProductSuggestResultEvent($request, $result, $context),
            ProductEvents::PRODUCT_SUGGEST_RESULT
        );

        return new ProductSuggestRouteResponse($result);
    }
}
