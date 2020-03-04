<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Events\ProductSuggestCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSuggestResultEvent;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use function Flag\next6025;

/**
 * @RouteScope(scopes={"store-api"})
 */
class ProductSuggestRoute implements ProductSuggestRouteInterface
{
    /**
     * @var SalesChannelRepositoryInterface
     */
    private $repository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ProductSearchBuilderInterface
     */
    private $searchBuilder;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct(
        SalesChannelRepositoryInterface $repository,
        ProductSearchBuilderInterface $searchBuilder,
        EventDispatcherInterface $eventDispatcher,
        SystemConfigService $systemConfigService
    ) {
        $this->repository = $repository;
        $this->eventDispatcher = $eventDispatcher;
        $this->searchBuilder = $searchBuilder;
        $this->systemConfigService = $systemConfigService;
    }

    /**
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
     * @Route("/store-api/v{version}/search-suggest", name="shop-api.search.suggest", methods={"POST"})
     */
    public function load(Request $request, SalesChannelContext $context): ProductSuggestRouteResponse
    {
        $criteria = new Criteria();

        $criteria->setLimit(10);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
        $criteria->addFilter(
            new ProductAvailableFilter($context->getSalesChannel()->getId(), ProductVisibilityDefinition::VISIBILITY_SEARCH)
        );

        if (next6025()) {
            $salesChannelId = $context->getSalesChannel()->getId();
            $hideCloseoutProductsWhenOutOfStock = $this->systemConfigService->get('core.listing.hideCloseoutProductsWhenOutOfStock', $salesChannelId);

            if ($hideCloseoutProductsWhenOutOfStock) {
                $criteria->addFilter(
                    new NotFilter(
                        NotFilter::CONNECTION_AND,
                        [
                            new EqualsFilter('product.isCloseout', true),
                            new EqualsFilter('product.available', false),
                        ]
                    )
                );
            }
        }

        $this->searchBuilder->build($request, $criteria, $context);

        $this->eventDispatcher->dispatch(
            new ProductSuggestCriteriaEvent($request, $criteria, $context),
            ProductEvents::PRODUCT_SUGGEST_CRITERIA
        );

        $result = $this->repository->search($criteria, $context);

        $result = ProductListingResult::createFrom($result);

        $this->eventDispatcher->dispatch(
            new ProductSuggestResultEvent($request, $result, $context),
            ProductEvents::PRODUCT_SUGGEST_RESULT
        );

        return new ProductSuggestRouteResponse($result);
    }
}
