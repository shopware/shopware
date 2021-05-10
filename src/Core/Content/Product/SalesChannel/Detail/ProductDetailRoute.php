<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Detail;

use OpenApi\Annotations as OA;
use Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoaderInterface;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\Product\SalesChannel\ProductCloseoutFilter;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
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
    private $productRepository;

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

    /**
     * @var SalesChannelCmsPageLoaderInterface
     */
    private $cmsPageLoader;

    /**
     * @var ProductDefinition
     */
    private $productDefinition;

    public function __construct(
        SalesChannelRepositoryInterface $productRepository,
        SystemConfigService $config,
        ProductConfiguratorLoader $configuratorLoader,
        CategoryBreadcrumbBuilder $breadcrumbBuilder,
        SalesChannelCmsPageLoaderInterface $cmsPageLoader,
        SalesChannelProductDefinition $productDefinition
    ) {
        $this->productRepository = $productRepository;
        $this->config = $config;
        $this->configuratorLoader = $configuratorLoader;
        $this->breadcrumbBuilder = $breadcrumbBuilder;
        $this->cmsPageLoader = $cmsPageLoader;
        $this->productDefinition = $productDefinition;
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
     *      summary="Fetch a single product",
     *      description="This route is used to load a single product with the corresponding details. In addition to loading the data, the best variant of the product is determined when a parent id is passed.",
     *      operationId="readProductDetail",
     *      tags={"Store API","Product"},
     *      @OA\Parameter(
     *          name="productId",
     *          description="Product ID",
     *          @OA\Schema(type="string"),
     *          in="path",
     *          required=true
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="Product information along with variant groups and options",
     *          @OA\JsonContent(ref="#/components/schemas/ProductDetailResponse")
     *     )
     * )
     * @Route("/store-api/product/{productId}", name="store-api.product.detail", methods={"POST"})
     */
    public function load(string $productId, Request $request, SalesChannelContext $context, Criteria $criteria): ProductDetailRouteResponse
    {
        $productId = $this->findBestVariant($productId, $context);

        $this->addFilters($context, $criteria);

        $criteria->setIds([$productId]);

        $product = $this->productRepository
            ->search($criteria, $context)
            ->first();

        if (!$product instanceof SalesChannelProductEntity) {
            throw new ProductNotFoundException($productId);
        }

        $product->setSeoCategory(
            $this->breadcrumbBuilder->getProductSeoCategory($product, $context)
        );

        $configurator = $this->configuratorLoader->load($product, $context);

        $pageId = $product->getCmsPageId();

        if ($pageId) {
            $resolverContext = new EntityResolverContext($context, $request, $this->productDefinition, $product);

            $pages = $this->cmsPageLoader->load(
                $request,
                $this->createCriteria($pageId, $request),
                $context,
                $product->getTranslation('slotConfig'),
                $resolverContext
            );

            if ($page = $pages->first()) {
                $product->setCmsPage($page);
            }
        }

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

        $variantId = $this->productRepository->searchIds($criteria, $context);

        return $variantId->firstId() ?? $productId;
    }

    private function createCriteria(string $pageId, Request $request): Criteria
    {
        $criteria = new Criteria([$pageId]);
        $criteria->setTitle('product::cms-page');

        $slots = $request->get('slots');

        if (\is_string($slots)) {
            $slots = explode('|', $slots);
        }

        if (!empty($slots) && \is_array($slots)) {
            $criteria
                ->getAssociation('sections.blocks')
                ->addFilter(new EqualsAnyFilter('slots.id', $slots));
        }

        return $criteria;
    }
}
