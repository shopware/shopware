<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Detail;

use OpenApi\Annotations as OA;
use Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\Product\SalesChannel\ProductCloseoutFilter;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"store-api"})
 */
class ProductDetailRoute extends AbstractProductDetailRoute
{
    /**
     * @var SalesChannelRepositoryInterface
     */
    private $repository;

    /**
     * @var SystemConfigService
     */
    private $config;

    /**
     * @var ProductConfiguratorLoader
     */
    private $configuratorLoader;

    /**
     * @var CategoryBreadcrumbBuilder
     */
    private $breadcrumbBuilder;

    public function __construct(
        SalesChannelRepositoryInterface $repository,
        SystemConfigService $config,
        ProductConfiguratorLoader $configuratorLoader,
        CategoryBreadcrumbBuilder $breadcrumbBuilder
    ) {
        $this->repository = $repository;
        $this->config = $config;
        $this->configuratorLoader = $configuratorLoader;
        $this->breadcrumbBuilder = $breadcrumbBuilder;
    }

    public function getDecorated(): AbstractProductDetailRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.3.2.0")
     * @Entity("product")
     * @OA\Post(
     *      path="/product/{productId}",
     *      summary="This route is used to load a single product with the corresponding details. In addition to loading the data, the best variant of the product is determined when a parent id is passed.",
     *      operationId="readProductDetail",
     *      tags={"Store API","Product"},
     *      @OA\Parameter(name="productId", description="Product ID", @OA\Schema(type="string"), in="path", required=true),
     *      @OA\Response(
     *          response="200",
     *          description="Found product",
     *          @OA\JsonContent(ref="#/components/schemas/product_flat")
     *     )
     * )
     * @Route("/store-api/v{version}/product/{productId}", name="store-api.product.detail", methods={"POST"})
     */
    public function load(string $productId, Request $request, SalesChannelContext $context, Criteria $criteria): ProductDetailRouteResponse
    {
        $productId = $this->findBestVariant($productId, $context);

        $this->addFilters($context, $criteria);

        $criteria->setIds([$productId]);

        $product = $this->repository
            ->search($criteria, $context)
            ->first();

        if (!$product instanceof SalesChannelProductEntity) {
            throw new ProductNotFoundException($productId);
        }

        $product->setSeoCategory(
            $this->breadcrumbBuilder->getProductSeoCategory($product, $context)
        );

        $configurator = $this->configuratorLoader->load($product, $context);

        return new ProductDetailRouteResponse($product, $configurator);
    }

    private function addFilters(SalesChannelContext $context, Criteria $criteria): void
    {
        $criteria->addFilter(
            new ProductAvailableFilter($context->getSalesChannel()->getId(), ProductVisibilityDefinition::VISIBILITY_LINK)
        );

        $salesChannelId = $context->getSalesChannel()->getId();

        $hideCloseoutProductsWhenOutOfStock = $this->config->get('core.listing.hideCloseoutProductsWhenOutOfStock', $salesChannelId);

        if ($hideCloseoutProductsWhenOutOfStock) {
            $filter = new ProductCloseoutFilter();
            $filter->addQuery(new EqualsFilter('product.parentId', null));
            $criteria->addFilter($filter);
        }
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    private function findBestVariant(string $productId, SalesChannelContext $context): string
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('product.parentId', $productId))
            ->addSorting(new FieldSorting('product.price'))
            ->addSorting(new FieldSorting('product.available'))
            ->setLimit(1);

        $variantId = $this->repository->searchIds($criteria, $context);

        if (\count($variantId->getIds()) > 0) {
            return $variantId->getIds()[0];
        }

        return $productId;
    }
}
