<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Detail;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoaderInterface;
use Shopware\Core\Content\Cms\Service\EntityCmsSlotConfigInheritanceBuilder;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationCollection;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductCloseoutFilterFactory;
use Shopware\Core\Content\Product\SalesChannel\Detail\Event\ResolveVariantIdEvent;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Adapter\Request\RequestParamHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Profiling\Profiler;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
#[Package('inventory')]
class ProductDetailRoute extends AbstractProductDetailRoute
{
    private const SKIP_CONFIGURATOR = 'skipConfigurator';
    private const SKIP_CMS_PAGE = 'skipCmsPage';

    /**
     * @internal
     *
     * @param SalesChannelRepository<SalesChannelProductCollection> $productRepository
     * @param EntityRepository<ProductTranslationCollection> $productTranslationRepository
     */
    public function __construct(
        private readonly SalesChannelRepository $productRepository,
        private readonly EntityRepository $productTranslationRepository,
        private readonly SystemConfigService $config,
        private readonly Connection $connection,
        private readonly ProductConfiguratorLoader $configuratorLoader,
        private readonly CategoryBreadcrumbBuilder $breadcrumbBuilder,
        private readonly SalesChannelCmsPageLoaderInterface $cmsPageLoader,
        private readonly EntityCmsSlotConfigInheritanceBuilder $cmsSlotConfigInheritanceBuilder,
        private readonly SalesChannelProductDefinition $productDefinition,
        private readonly AbstractProductCloseoutFilterFactory $productCloseoutFilterFactory,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly CacheTagCollector $cacheTagCollector,
    ) {
    }

    public static function buildName(string $parentId): string
    {
        return EntityCacheKeyGenerator::buildProductTag($parentId);
    }

    public function getDecorated(): AbstractProductDetailRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/product/{productId}',
        name: 'store-api.product.detail',
        defaults: [
            PlatformRequest::ATTRIBUTE_ENTITY => ProductDefinition::ENTITY_NAME,
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
        ],
        methods: [Request::METHOD_POST, Request::METHOD_GET]
    )]
    public function load(string $productId, Request $request, SalesChannelContext $context, Criteria $criteria): ProductDetailRouteResponse
    {
        return Profiler::trace('product-detail-route', function () use ($productId, $request, $context, $criteria) {
            $requestedProductId = $productId;
            [$mainVariantId, $parentProductId] = $this->checkVariantListingConfig($productId, $context);
            $searchVariantId = $this->resolveSearchVariantId(
                $requestedProductId,
                $parentProductId,
                $this->getSearchTerm($request),
                $context
            );
            $resolveVariantIdEvent = new ResolveVariantIdEvent(
                $productId,
                $searchVariantId ?? $mainVariantId,
                $context,
            );

            $this->dispatcher->dispatch($resolveVariantIdEvent);

            $productId = $this->resolveCandidateProductId(
                $requestedProductId,
                $parentProductId,
                $resolveVariantIdEvent->getResolvedVariantId(),
                $context
            );

            $this->addFilters($context, $criteria);

            $criteria->setIds([$productId]);
            $criteria->setTitle('product-detail-route');

            $loadCmsPage = !$request->query->getBoolean(self::SKIP_CMS_PAGE);
            $product = $this->productRepository->search($criteria, $context)->getEntities()->first();

            if (!$product instanceof SalesChannelProductEntity) {
                throw ProductException::productNotFound($productId);
            }

            $parent = $product->getParentId() ?? $product->getId();

            $this->cacheTagCollector->addTag(EntityCacheKeyGenerator::buildProductTag($parent));

            $product->setSeoCategory(
                $this->getBreadcrumbCategory($request, $product, $context)
            );

            $loadConfigurator = !$request->query->getBoolean(self::SKIP_CONFIGURATOR);
            $configurator = $loadConfigurator ? $this->configuratorLoader->load($product, $context) : null;

            $pageId = $product->getCmsPageId();
            if ($loadCmsPage && $pageId) {
                $slotConfig = $this->buildMergedCmsSlotConfig($product, $context);

                // clone product to prevent recursion encoding (see NEXT-17603)
                $resolverContext = new EntityResolverContext($context, $request, $this->productDefinition, clone $product);

                $pages = $this->cmsPageLoader->load(
                    $request,
                    $this->createCriteria($pageId, $request),
                    $context,
                    $slotConfig,
                    $resolverContext
                );

                $cmsPage = $pages->first();
                if ($cmsPage instanceof CmsPageEntity) {
                    $product->setCmsPage($cmsPage);
                }
            }

            return new ProductDetailRouteResponse($product, $configurator);
        });
    }

    private function addFilters(SalesChannelContext $context, Criteria $criteria): void
    {
        $criteria->addFilter(
            new ProductAvailableFilter($context->getSalesChannelId(), ProductVisibilityDefinition::VISIBILITY_LINK)
        );

        $this->addCloseoutFilter($context, $criteria);
    }

    /**
     * @return array<string, array<string, mixed>>|null
     */
    private function buildMergedCmsSlotConfig(SalesChannelProductEntity $product, SalesChannelContext $context): ?array
    {
        return $this->cmsSlotConfigInheritanceBuilder->build(
            $this->loadProductTranslations($product, $context),
            $context,
        );
    }

    private function loadProductTranslations(SalesChannelProductEntity $product, SalesChannelContext $context): ?ProductTranslationCollection
    {
        $productIds = array_filter([$product->getParentId(), $product->getId()]);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('productId', $productIds));
        $criteria->addFilter(new EqualsFilter('productVersionId', $context->getVersionId()));

        $translations = $this->productTranslationRepository->search($criteria, $context->getContext())->getEntities();

        if ($translations->count() === 0) {
            return null;
        }

        return $this->buildInheritedProductTranslations($translations, $product);
    }

    private function buildInheritedProductTranslations(ProductTranslationCollection $translations, SalesChannelProductEntity $product): ProductTranslationCollection
    {
        $effectiveTranslations = [];
        $parentId = $product->getParentId();

        foreach ($translations as $translation) {
            if ($translation->getSlotConfig() === null) {
                continue;
            }

            $languageId = $translation->getLanguageId();

            if ($translation->getProductId() === $parentId) {
                $effectiveTranslations[$languageId] ??= $translation;

                continue;
            }

            if ($translation->getProductId() === $product->getId()) {
                $effectiveTranslations[$languageId] = $translation;
            }
        }

        return new ProductTranslationCollection(array_values($effectiveTranslations));
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function checkVariantListingConfig(string $productId, SalesChannelContext $context): array
    {
        if (!Uuid::isValid($productId)) {
            return [null, null];
        }

        $productData = $this->connection->fetchAssociative(
            '# product-detail-route::check-variant-listing-config
            SELECT
                variant_listing_config as variantListingConfig,
                parent_id as parentId
            FROM product
            WHERE id = :id
            AND version_id = :versionId',
            [
                'id' => Uuid::fromHexToBytes($productId),
                'versionId' => Uuid::fromHexToBytes($context->getVersionId()),
            ]
        );

        if (empty($productData)) {
            return [null, null];
        }

        $mainVariantId = null;
        if ($productData['variantListingConfig'] !== null) {
            $variantListingConfig = json_decode((string) $productData['variantListingConfig'], true, 512, \JSON_THROW_ON_ERROR);

            if (
                !isset($variantListingConfig['displayParent'])
                || (bool) $variantListingConfig['displayParent'] !== true
                || isset($variantListingConfig['mainVariantId'])
            ) {
                $mainVariantId = $variantListingConfig['mainVariantId'] ?? null;
            }
        }

        return [$mainVariantId, $productData['parentId'] ?? $productId];
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    private function findBestVariant(string $productId, SalesChannelContext $context): string
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('product.parentId', $productId))
            ->addSorting(new FieldSorting('product.available', FieldSorting::DESCENDING))
            ->addSorting(new FieldSorting('product.price'))
            ->setLimit(1);

        $this->addCloseoutFilter($context, $criteria);
        $criteria->setTitle('product-detail-route::find-best-variant');
        $variantId = $this->productRepository->searchIds($criteria, $context);

        return $variantId->firstId() ?? $productId;
    }

    private function findBestVariantByTerm(string $term, string $productId, SalesChannelContext $context): ?string
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('product.parentId', $productId))
            ->setLimit(1);

        $this->addCloseoutFilter($context, $criteria);
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->setTerm($term);

        $criteria->setTitle('product-detail-route::find-best-variant-by-term');

        return $this->productRepository->searchIds($criteria, $context)->firstId();
    }

    private function addCloseoutFilter(SalesChannelContext $context, Criteria $criteria): void
    {
        if (!$this->hideCloseoutProductsWhenOutOfStock($context)) {
            return;
        }

        $criteria->addFilter($this->productCloseoutFilterFactory->create($context));
    }

    private function hideCloseoutProductsWhenOutOfStock(SalesChannelContext $context): bool
    {
        return $this->config->getBool('core.listing.hideCloseoutProductsWhenOutOfStock', $context->getSalesChannelId());
    }

    private function getSearchTerm(Request $request): ?string
    {
        $searchTerm = $request->query->get('search');

        if (!\is_string($searchTerm) || $searchTerm === '') {
            return null;
        }

        return $searchTerm;
    }

    private function resolveSearchVariantId(
        string $requestedProductId,
        ?string $parentProductId,
        ?string $searchTerm,
        SalesChannelContext $context
    ): ?string {
        if (
            $searchTerm === null
            || !$this->isParentProductRequest($requestedProductId, $parentProductId)
            || !$this->config->getBool('core.listing.findBestVariant', $context->getSalesChannelId())
        ) {
            return null;
        }

        return $this->findBestVariantByTerm($searchTerm, $requestedProductId, $context);
    }

    private function resolveCandidateProductId(
        string $requestedProductId,
        ?string $parentProductId,
        ?string $resolvedVariantId,
        SalesChannelContext $context
    ): string {
        if ($resolvedVariantId !== null) {
            return $resolvedVariantId;
        }

        if (!$this->isParentProductRequest($requestedProductId, $parentProductId)) {
            return $requestedProductId;
        }

        return $this->findBestVariant($requestedProductId, $context);
    }

    private function isParentProductRequest(string $requestedProductId, ?string $parentProductId): bool
    {
        return $parentProductId !== null && $requestedProductId === $parentProductId;
    }

    private function createCriteria(string $pageId, Request $request): Criteria
    {
        $criteria = new Criteria([$pageId]);
        $criteria->setTitle('product::cms-page');

        $slots = RequestParamHelper::get($request, 'slots');

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

    private function getBreadcrumbCategory(Request $request, ProductEntity $product, SalesChannelContext $context): ?CategoryEntity
    {
        if (Feature::isActive('BREADCRUMB_REWORK') || Feature::isActive('v6.8.0.0')) {
            if ($this->config->getBool('core.listing.buildBreadcrumbByReferrerCategory', $context->getSalesChannelId())) {
                $referrerCategoryId = $request->query->get('referrerCategoryId');

                if ($referrerCategoryId !== null && \in_array($referrerCategoryId, $product->getCategoryIds() ?? [], true)) {
                    return $this->breadcrumbBuilder->loadCategory($referrerCategoryId, $context->getContext());
                }
            }
        }

        return $this->breadcrumbBuilder->getProductSeoCategory($product, $context);
    }
}
