<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\CrossSelling;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingCollection;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Events\ProductCrossSellingCriteriaLoadEvent;
use Shopware\Core\Content\Product\Events\ProductCrossSellingIdsCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductCrossSellingsLoadedEvent;
use Shopware\Core\Content\Product\Events\ProductCrossSellingStreamCriteriaEvent;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductCloseoutFilterFactory;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\ProductStream\Service\AbstractProductStreamBuilder;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('inventory')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class ProductCrossSellingRoute extends AbstractProductCrossSellingRoute
{
    private const ID_PLACEHOLDER = '00000000000000000000000000000000';

    /**
     * @internal
     *
     * @param EntityRepository<ProductCrossSellingCollection> $crossSellingRepository
     * @param SalesChannelRepository<ProductCollection> $productRepository
     */
    public function __construct(
        private readonly EntityRepository $crossSellingRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ProductStreamBuilderInterface|AbstractProductStreamBuilder $productStreamBuilder,
        private readonly SalesChannelRepository $productRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly ProductListingLoader $listingLoader,
        private readonly AbstractProductCloseoutFilterFactory $productCloseoutFilterFactory,
        private readonly CacheTagCollector $cacheTagCollector,
        private readonly Connection $connection,
    ) {
    }

    public function getDecorated(): AbstractProductCrossSellingRoute
    {
        throw new DecorationPatternException(self::class);
    }

    public static function buildName(string $id): string
    {
        return EntityCacheKeyGenerator::buildProductTag($id);
    }

    #[Route(
        path: '/store-api/product/{productId}/cross-selling',
        name: 'store-api.product.cross-selling',
        methods: [Request::METHOD_POST, Request::METHOD_GET],
        defaults: [PlatformRequest::ATTRIBUTE_ENTITY => ProductDefinition::ENTITY_NAME, PlatformRequest::ATTRIBUTE_HTTP_CACHE => true]
    )]
    public function load(string $productId, Request $request, SalesChannelContext $context, Criteria $criteria): ProductCrossSellingRouteResponse
    {
        $crossSellings = $this->loadCrossSellings($productId, $context);

        $rootProductId = $crossSellings->count() > 0
            ? $this->fetchRootProductId($productId, $context)
            : $productId;

        $elements = new CrossSellingElementCollection();

        /** @var array<string, array{criteria: Criteria, ids: list<string>, elements: list<array{element: CrossSellingElement, ids: list<string>}>}> $grouped */
        $grouped = [];

        foreach ($crossSellings as $crossSelling) {
            // CrossSellingElement is typed against ProductCollection, a field selection would load PartialEntity instances
            $clone = clone $criteria;
            $clone->resetFields();

            if ($this->useProductStream($crossSelling)) {
                $elements->add($this->loadByStream($crossSelling, $rootProductId, $context, $clone));

                continue;
            }

            $element = $this->createElement($crossSelling);
            $elements->add($element);

            $ids = $this->assignedProductIds($crossSelling);

            if ($ids === []) {
                continue;
            }

            $prepared = $this->prepareIdsCriteria($crossSelling, $ids, $context, $clone);

            // cross sellings that ask for the same thing apart from their ids are answered by one query; a subscriber
            // that differentiates the criteria per cross selling keeps its own query
            $hash = $this->hashWithoutIds($prepared);

            $grouped[$hash]['criteria'] ??= $prepared;
            $grouped[$hash]['ids'] = array_merge($grouped[$hash]['ids'] ?? [], $ids);
            $grouped[$hash]['elements'][] = ['element' => $element, 'ids' => $ids];
        }

        $this->fillProducts($grouped, $context);

        $this->eventDispatcher->dispatch(new ProductCrossSellingsLoadedEvent($elements, $context));

        $tags = [self::buildName($productId)];

        if (Feature::isActive('v6.8.0.0') || Feature::isActive('CACHE_REWORK')) {
            $tags = array_merge($tags, $this->getCrossSellingTags($elements));
        }

        $this->cacheTagCollector->addTag(...$tags);

        return new ProductCrossSellingRouteResponse($elements);
    }

    /**
     * @return list<string>
     */
    private function getCrossSellingTags(CrossSellingElementCollection $elements): array
    {
        $tags = [];

        foreach ($elements as $element) {
            foreach ($element->getProducts() as $product) {
                $tags[] = EntityCacheKeyGenerator::buildProductTag($product->getId());

                if ($product->getParentId() !== null) {
                    $tags[] = EntityCacheKeyGenerator::buildProductTag($product->getParentId());
                }
            }
        }

        return array_values(array_unique(array_filter($tags)));
    }

    private function loadCrossSellings(string $productId, SalesChannelContext $context): ProductCrossSellingCollection
    {
        $criteria = new Criteria();
        $criteria
            ->setTitle('product-cross-selling-route')
            ->addAssociation('assignedProducts')
            ->addFilter(new EqualsFilter('product.id', $productId))
            ->addFilter(new EqualsFilter('active', 1))
            ->addSorting(new FieldSorting('position', FieldSorting::ASCENDING));

        $this->eventDispatcher->dispatch(
            new ProductCrossSellingCriteriaLoadEvent($criteria, $context)
        );

        return $this->crossSellingRepository->search($criteria, $context->getContext())->getEntities();
    }

    /**
     * @param string $rootProductId id of the currently viewed product, or of its parent if it is a variant
     */
    private function loadByStream(ProductCrossSellingEntity $crossSelling, string $rootProductId, SalesChannelContext $context, Criteria $criteria): CrossSellingElement
    {
        $productStreamId = $crossSelling->getProductStreamId();
        \assert(\is_string($productStreamId));

        $this->cacheTagCollector->addTag(
            EntityCacheKeyGenerator::buildStreamTag($productStreamId)
        );

        $productStreamBuilder = $this->productStreamBuilder;
        if ($productStreamBuilder instanceof AbstractProductStreamBuilder) {
            $productStreamBuilder->enrichCriteria($criteria, $productStreamId, $context->getContext());
        } else {
            $criteria->addFilter(...$productStreamBuilder->buildFilters($productStreamId, $context->getContext()));
        }

        $criteria
            ->addFilter($this->createProductExclusionFilter($rootProductId))
            ->setOffset(0)
            ->setLimit($crossSelling->getLimit())
            ->addSorting($crossSelling->getSorting());

        $criteria = $this->handleAvailableStock($criteria, $context);

        $this->eventDispatcher->dispatch(
            new ProductCrossSellingStreamCriteriaEvent($crossSelling, $criteria, $context)
        );

        // a subscriber might have added a field selection
        $criteria->resetFields();

        $products = $this->listingLoader->load($criteria, $context)->getEntities();

        $element = new CrossSellingElement();
        $element->setCrossSelling($crossSelling);
        $element->setProducts($products);
        $element->setStreamId($crossSelling->getProductStreamId());

        $element->setTotal($products->count());

        return $element;
    }

    private function createElement(ProductCrossSellingEntity $crossSelling): CrossSellingElement
    {
        $element = new CrossSellingElement();
        $element->setCrossSelling($crossSelling);
        $element->setProducts(new ProductCollection());
        $element->setTotal(0);

        return $element;
    }

    /**
     * @return list<string>
     */
    private function assignedProductIds(ProductCrossSellingEntity $crossSelling): array
    {
        $assigned = $crossSelling->getAssignedProducts();

        if (!$assigned) {
            return [];
        }

        $assigned->sortByPosition();

        return array_values($assigned->getProductIds());
    }

    /**
     * @param list<string> $ids
     */
    private function prepareIdsCriteria(ProductCrossSellingEntity $crossSelling, array $ids, SalesChannelContext $context, Criteria $criteria): Criteria
    {
        $criteria->setIds($ids);
        $criteria->addFilter(new ProductAvailableFilter(
            $context->getSalesChannelId(),
            ProductVisibilityDefinition::VISIBILITY_ALL
        ));
        $criteria->addAssociation('options.group');

        $criteria = $this->handleAvailableStock($criteria, $context);

        $this->eventDispatcher->dispatch(
            new ProductCrossSellingIdsCriteriaEvent($crossSelling, $criteria, $context)
        );

        // a subscriber might have added a field selection
        $criteria->resetFields();

        return $criteria;
    }

    /**
     * The ids are what differs between cross sellings, so they are left out of the hash and merged instead.
     */
    private function hashWithoutIds(Criteria $criteria): string
    {
        $withoutIds = clone $criteria;
        // the criteria rejects an empty id list, so every copy gets the same placeholder instead
        $withoutIds->setIds([self::ID_PLACEHOLDER]);

        return Hasher::hash($withoutIds);
    }

    /**
     * @param array<string, array{criteria: Criteria, ids: list<string>, elements: list<array{element: CrossSellingElement, ids: list<string>}>}> $grouped
     */
    private function fillProducts(array $grouped, SalesChannelContext $context): void
    {
        foreach ($grouped as $group) {
            $criteria = $group['criteria'];
            $criteria->setIds(array_values(array_unique($group['ids'])));

            $products = $this->productRepository->search($criteria, $context)->getEntities();

            foreach ($group['elements'] as ['element' => $element, 'ids' => $ids]) {
                $own = $products->getList($ids);
                $own->sortByIdArray($ids);

                $element->setProducts($own);
                $element->setTotal($own->count());
            }
        }
    }

    /**
     * Excludes the currently viewed product from its own cross-selling. For variants, the complete variant
     * family is excluded: variant grouping and main variant resolution in {@see ProductListingLoader} would
     * otherwise resolve a sibling variant back to the currently viewed product or to its parent.
     */
    private function createProductExclusionFilter(string $rootProductId): Filter
    {
        return new NotFilter(NotFilter::CONNECTION_OR, [
            new EqualsFilter('product.id', $rootProductId),
            new EqualsFilter('product.parentId', $rootProductId),
        ]);
    }

    /**
     * Cross-sellings are inherited, so the currently viewed product is not necessarily the one the
     * cross-selling is assigned to. Resolve the root of the variant family the product belongs to.
     */
    private function fetchRootProductId(string $productId, SalesChannelContext $context): string
    {
        $parentId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(parent_id)) FROM product WHERE id = :id AND version_id = :versionId',
            [
                'id' => Uuid::fromHexToBytes($productId),
                'versionId' => Uuid::fromHexToBytes($context->getVersionId()),
            ]
        );

        return \is_string($parentId) ? $parentId : $productId;
    }

    private function handleAvailableStock(Criteria $criteria, SalesChannelContext $context): Criteria
    {
        $salesChannelId = $context->getSalesChannelId();
        $hide = $this->systemConfigService->get('core.listing.hideCloseoutProductsWhenOutOfStock', $salesChannelId);

        if (!$hide) {
            return $criteria;
        }

        $closeoutFilter = $this->productCloseoutFilterFactory->create($context);
        $criteria->addFilter($closeoutFilter);

        return $criteria;
    }

    private function useProductStream(ProductCrossSellingEntity $crossSelling): bool
    {
        return $crossSelling->getType() === ProductCrossSellingDefinition::TYPE_PRODUCT_STREAM
            && $crossSelling->getProductStreamId() !== null;
    }
}
