<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Detail;

use Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoaderInterface;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductCloseoutFilterFactory;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Profiling\Profiler;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('inventory')]
class ProductDetailRoute extends AbstractProductDetailRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SalesChannelRepository $productRepository,
        private readonly SystemConfigService $config,
        private readonly ProductConfiguratorLoader $configuratorLoader,
        private readonly CategoryBreadcrumbBuilder $breadcrumbBuilder,
        private readonly SalesChannelCmsPageLoaderInterface $cmsPageLoader,
        private readonly SalesChannelProductDefinition $productDefinition,
        private readonly AbstractProductCloseoutFilterFactory $productCloseoutFilterFactory
    ) {
    }

    public function getDecorated(): AbstractProductDetailRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/product/{productId}', name: 'store-api.product.detail', methods: ['POST'], defaults: ['_entity' => 'product'])]
    public function load(string $productId, Request $request, SalesChannelContext $context, Criteria $criteria): ProductDetailRouteResponse
    {
        return Profiler::trace('product-detail-route', function () use ($productId, $request, $context, $criteria) {
            $mainVariantId = $this->checkVariantListingConfig($productId, $context);

            $productId = $mainVariantId ?? $this->findBestVariant($productId, $context);

            $this->addFilters($context, $criteria);

            $criteria->setIds([$productId]);
            $criteria->setTitle('product-detail-route');

            $product = $this->productRepository
                ->search($criteria, $context)
                ->first();

            if (!($product instanceof SalesChannelProductEntity)) {
                throw new ProductNotFoundException($productId);
            }

            $product->setSeoCategory(
                $this->breadcrumbBuilder->getProductSeoCategory($product, $context)
            );

            $configurator = $this->configuratorLoader->load($product, $context);

            $pageId = $product->getCmsPageId();

            if ($pageId) {
                // clone product to prevent recursion encoding (see NEXT-17603)
                $resolverContext = new EntityResolverContext($context, $request, $this->productDefinition, clone $product);

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
        });
    }

    private function addFilters(SalesChannelContext $context, Criteria $criteria): void
    {
        $criteria->addFilter(
            new ProductAvailableFilter($context->getSalesChannel()->getId(), ProductVisibilityDefinition::VISIBILITY_LINK)
        );

        $salesChannelId = $context->getSalesChannel()->getId();

        $hideCloseoutProductsWhenOutOfStock = $this->config->get('core.listing.hideCloseoutProductsWhenOutOfStock', $salesChannelId);

        if ($hideCloseoutProductsWhenOutOfStock) {
            $filter = $this->productCloseoutFilterFactory->create($context);
            $filter->addQuery(new EqualsFilter('product.parentId', null));
            $criteria->addFilter($filter);
        }
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    private function checkVariantListingConfig(string $productId, SalesChannelContext $context): ?string
    {
        /** @var SalesChannelProductEntity|null $product */
        $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();

        if ($product === null || $product->getParentId() !== null) {
            return null;
        }

        if (($listingConfig = $product->getVariantListingConfig()) === null || $listingConfig->getDisplayParent() !== true) {
            return null;
        }

        return $listingConfig->getMainVariantId();
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

        $criteria->setTitle('product-detail-route::find-best-variant');
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
