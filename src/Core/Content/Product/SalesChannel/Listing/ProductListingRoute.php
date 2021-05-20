<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing;

use OpenApi\Annotations as OA;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
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
class ProductListingRoute extends AbstractProductListingRoute
{
    /**
     * @var ProductListingLoader
     */
    private $listingLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var EntityRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var ProductStreamBuilderInterface
     */
    private $productStreamBuilder;

    public function __construct(
        ProductListingLoader $listingLoader,
        EventDispatcherInterface $eventDispatcher,
        EntityRepositoryInterface $categoryRepository,
        ProductStreamBuilderInterface $productStreamBuilder
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->listingLoader = $listingLoader;
        $this->categoryRepository = $categoryRepository;
        $this->productStreamBuilder = $productStreamBuilder;
    }

    public function getDecorated(): AbstractProductListingRoute
    {
        throw new DecorationPatternException(self::class);
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
        $criteria->addFilter(
            new ProductAvailableFilter($context->getSalesChannel()->getId(), ProductVisibilityDefinition::VISIBILITY_ALL)
        );

        /** @var CategoryEntity $category */
        $category = $this->categoryRepository->search(new Criteria([$categoryId]), $context->getContext())->first();

        $streamId = $this->extendCriteria($context, $criteria, $category);

        $result = $this->listingLoader->load($criteria, $context);

        $result = ProductListingResult::createFrom($result);

        $result->addCurrentFilter('navigationId', $categoryId);

        $this->eventDispatcher->dispatch(
            new ProductListingResultEvent($request, $result, $context)
        );

        $result->setStreamId($streamId);

        return new ProductListingRouteResponse($result);
    }

    private function extendCriteria(SalesChannelContext $salesChannelContext, Criteria $criteria, CategoryEntity $category): ?string
    {
        if ($category->getProductAssignmentType() === CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT_STREAM && $category->getProductStreamId() !== null) {
            $filters = $this->productStreamBuilder->buildFilters(
                $category->getProductStreamId(),
                $salesChannelContext->getContext()
            );

            $criteria->addFilter(...$filters);

            return $category->getProductStreamId();
        }

        $criteria->addFilter(
            new EqualsFilter('product.categoriesRo.id', $category->getId())
        );

        return null;
    }
}
