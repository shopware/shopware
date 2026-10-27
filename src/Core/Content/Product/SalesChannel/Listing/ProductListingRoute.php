<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Extension\ProductListingCriteriaExtension;
use Shopware\Core\Content\Product\Extension\ProductListingRouteExtension;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\ProductStream\Exception\EmptyProductStreamException;
use Shopware\Core\Content\ProductStream\Exception\NoFilterException;
use Shopware\Core\Content\ProductStream\Service\AbstractProductStreamBuilder;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('inventory')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class ProductListingRoute extends AbstractProductListingRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<EntityCollection<PartialEntity>> $categoryRepository
     */
    public function __construct(
        private readonly ProductListingLoader $listingLoader,
        private readonly EntityRepository $categoryRepository,
        private readonly ProductStreamBuilderInterface|AbstractProductStreamBuilder $productStreamBuilder,
        private readonly CacheTagCollector $cacheTagCollector,
        private readonly ExtensionDispatcher $extensions,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getDecorated(): AbstractProductListingRoute
    {
        throw new DecorationPatternException(self::class);
    }

    public static function buildName(string $categoryId): string
    {
        return 'product-listing-' . $categoryId;
    }

    #[Route(
        path: '/store-api/product-listing/{categoryId}',
        name: 'store-api.product.listing',
        methods: [Request::METHOD_POST, Request::METHOD_GET],
        defaults: [PlatformRequest::ATTRIBUTE_ENTITY => ProductDefinition::ENTITY_NAME, PlatformRequest::ATTRIBUTE_HTTP_CACHE => true]
    )]
    public function load(string $categoryId, Request $request, SalesChannelContext $context, Criteria $criteria): ProductListingRouteResponse
    {
        return $this->extensions->publish(
            name: ProductListingRouteExtension::NAME,
            extension: new ProductListingRouteExtension($categoryId, $request, $context, $criteria),
            function: fn (string $categoryId, Request $request, SalesChannelContext $context, Criteria $criteria): ProductListingRouteResponse => $this->_load($categoryId, $context, $criteria),
        );
    }

    private function _load(string $categoryId, SalesChannelContext $context, Criteria $criteria): ProductListingRouteResponse
    {
        $criteria->addFilter(
            new ProductAvailableFilter($context->getSalesChannelId(), ProductVisibilityDefinition::VISIBILITY_ALL)
        );
        $criteria->setTitle('product-listing-route::loading');

        $category = $this->loadCategory($categoryId, $context);
        if (!$category) {
            throw ProductException::categoryNotFound($categoryId);
        }

        $streamSubcategories = $this->loadStreamSubcategories($categoryId, $context);

        $this->addCacheTags($category, $streamSubcategories);

        $criteria = $this->extensions->publish(
            name: ProductListingCriteriaExtension::NAME,
            extension: new ProductListingCriteriaExtension($criteria, $context, $categoryId),
            function: function ($criteria, $context, $categoryId) use ($category, $streamSubcategories): Criteria {
                $this->extendCriteria($context, $criteria, $category, $streamSubcategories);

                return $criteria;
            }
        );

        $entities = $this->listingLoader->load($criteria, $context);

        $result = ProductListingResult::createFrom($entities);
        $result->addState(...$entities->getStates());

        $result->setStreamId($category->get('productStreamId'));

        return new ProductListingRouteResponse($result);
    }

    private function loadCategory(string $categoryId, SalesChannelContext $context): ?PartialEntity
    {
        $criteria = new Criteria([$categoryId]);
        $criteria->setTitle('product-listing-route::category-loading');
        $criteria->addFields(['productAssignmentType', 'productStreamId']);
        $criteria->setLimit(1);

        return $this->categoryRepository->search($criteria, $context->getContext())->getEntities()->first();
    }

    /**
     * Loads the subcategories that show a dynamic product group. The condition on the group reference lets the
     * database read them through the index of that reference instead of scanning the whole category tree.
     * EqualsAnyFilter compiles null to IS NULL, which the database negates to IS NOT NULL and reads from the index.
     * EqualsFilter compiles it to <=> NULL, whose negation the database checks against every category.
     *
     * @return EntityCollection<PartialEntity>
     */
    private function loadStreamSubcategories(string $categoryId, SalesChannelContext $context): EntityCollection
    {
        $criteria = new Criteria();
        $criteria->setTitle('product-listing-route::stream-subcategory-loading');
        $criteria->addFields(['productAssignmentType', 'productStreamId', 'path']);
        $criteria->addFilter(
            new EqualsFilter('productAssignmentType', CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT_STREAM),
            new NotFilter(NotFilter::CONNECTION_AND, [new EqualsAnyFilter('productStreamId', [null])]),
            new ContainsFilter('path', '|' . $categoryId . '|'),
        );

        return $this->categoryRepository->search($criteria, $context->getContext())->getEntities();
    }

    /**
     * A listing shows the products of the requested category itself and those of the dynamic product groups of all
     * its subcategories
     *
     * @param EntityCollection<PartialEntity> $streamSubcategories
     */
    private function extendCriteria(SalesChannelContext $context, Criteria $criteria, PartialEntity $category, EntityCollection $streamSubcategories): void
    {
        $filters = [];

        $productStreamId = $this->getProductStreamId($category);
        if ($productStreamId === null) {
            $manualCategoryIds = [$category->getId()];
        } else {
            try {
                $streamCriteria = $this->loadProductStreamCriteria($productStreamId, $context);
            } catch (EmptyProductStreamException) {
                // An empty group selects all products, as in the product export, so nothing below it can add more
                return;
            } catch (NoFilterException $exception) {
                $this->logger->warning(
                    'Product stream configured for category listing has no usable filters.',
                    [
                        'productStreamId' => $productStreamId,
                        'exception' => $exception,
                    ]
                );

                // a broken group selects no products, while the categories below it still add theirs
                $streamCriteria = new Criteria();
                $streamCriteria->addFilter(new EqualsFilter('product.id', null));
            }

            // only the requested category's own group decides how its listing is displayed, for example variants as
            // single products. Groups of subcategories only add products.
            $criteria->addState(...$streamCriteria->getStates());
            $filters[] = $this->getStreamFilter($streamCriteria);

            // a group category does not show its own manual assignments, but those of the manual categories below it
            $manualCategoryIds = $this->loadManualChildCategoryIds(
                [$category->getId(), ...array_values($streamSubcategories->getIds())],
                $context
            );
        }

        foreach ($streamSubcategories as $subcategory) {
            $filters[] = $this->getSubcategoryStreamFilter($category, $subcategory, $context);
        }

        if ($manualCategoryIds !== []) {
            // categoriesRo holds every category of a product's tree, so this also matches the products assigned
            // anywhere below these categories
            $filters[] = new EqualsAnyFilter('product.categoriesRo.id', $manualCategoryIds);
        }

        $filters = array_values(array_filter($filters));
        if ($filters !== []) {
            $criteria->addFilter(\count($filters) === 1 ? $filters[0] : new OrFilter($filters));
        }
    }

    /**
     * An unusable group of a subcategory is skipped, so that it does not break the listings of all categories above it
     */
    private function getSubcategoryStreamFilter(PartialEntity $category, PartialEntity $subcategory, SalesChannelContext $context): ?Filter
    {
        $productStreamId = $this->getProductStreamId($subcategory);
        if ($productStreamId === null) {
            return null;
        }

        try {
            return $this->getStreamFilter($this->loadProductStreamCriteria($productStreamId, $context));
        } catch (NoFilterException|EntityNotFoundException $exception) {
            $this->logger->warning(
                'Product stream of a subcategory cannot be used and is skipped in the category listing.',
                [
                    'categoryId' => $category->getId(),
                    'subcategoryId' => $subcategory->getId(),
                    'productStreamId' => $productStreamId,
                    'exception' => $exception,
                ]
            );

            return null;
        }
    }

    /**
     * Returns the manual categories directly below the given ones. Deeper ones are not needed, because categoriesRo
     * also covers everything assigned below them.
     *
     * @param list<string> $parentIds
     *
     * @return list<string>
     */
    private function loadManualChildCategoryIds(array $parentIds, SalesChannelContext $context): array
    {
        $criteria = new Criteria();
        $criteria->setTitle('product-listing-route::manual-subcategory-loading');
        $criteria->addFields(['productAssignmentType', 'productStreamId']);
        $criteria->addFilter(new EqualsAnyFilter('parentId', $parentIds));

        $children = $this->categoryRepository->search($criteria, $context->getContext())->getEntities();

        return array_values($children->filter(fn (PartialEntity $child) => $this->getProductStreamId($child) === null)->getIds());
    }

    /**
     * Resolves the group into its own criteria, so its filters can be combined with the other sources of the listing
     */
    private function loadProductStreamCriteria(string $productStreamId, SalesChannelContext $context): Criteria
    {
        $streamCriteria = new Criteria();

        $productStreamBuilder = $this->productStreamBuilder;
        if ($productStreamBuilder instanceof AbstractProductStreamBuilder) {
            $productStreamBuilder->enrichCriteria($streamCriteria, $productStreamId, $context->getContext());
        } else {
            $streamCriteria->addFilter(...$productStreamBuilder->buildFilters($productStreamId, $context->getContext()));
        }

        return $streamCriteria;
    }

    /**
     * Combines a group's filters into one, so that all its conditions still have to match when it is ORed with the
     * other sources of the listing
     */
    private function getStreamFilter(Criteria $streamCriteria): ?Filter
    {
        $filters = array_values($streamCriteria->getFilters());

        return match (\count($filters)) {
            0 => null,
            1 => $filters[0],
            default => new AndFilter($filters),
        };
    }

    /**
     * Returns the id of the dynamic product group the category shows, or null for a category with manual assignment
     */
    private function getProductStreamId(PartialEntity $category): ?string
    {
        $productStreamId = $category->get('productStreamId');

        if (
            $category->get('productAssignmentType') !== CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT_STREAM
            || !\is_string($productStreamId)
            || $productStreamId === ''
        ) {
            return null;
        }

        return $productStreamId;
    }

    /**
     * Tags the listing with the categories it depends on: the requested one and, for every subcategory with a dynamic
     * product group, each category on the way down to it. Moving or deleting any of them invalidates the listing.
     *
     * @param EntityCollection<PartialEntity> $streamSubcategories
     */
    private function addCacheTags(PartialEntity $category, EntityCollection $streamSubcategories): void
    {
        $categoryIds = [$category->getId()];
        $productStreamIds = [$this->getProductStreamId($category)];

        foreach ($streamSubcategories as $subcategory) {
            array_push($categoryIds, ...$this->getCategoryIdsDownTo($category->getId(), $subcategory));
            $productStreamIds[] = $this->getProductStreamId($subcategory);
        }

        $tags = [
            ...array_map(self::buildName(...), $categoryIds),
            ...array_map(EntityCacheKeyGenerator::buildStreamTag(...), array_filter($productStreamIds)),
        ];

        $this->cacheTagCollector->addTag(...array_values(array_unique($tags)));
    }

    /**
     * @return list<string> the categories below $categoryId on the way down to the subcategory, the subcategory included
     */
    private function getCategoryIdsDownTo(string $categoryId, PartialEntity $subcategory): array
    {
        $path = $subcategory->get('path');
        $pathIds = \is_string($path) ? array_values(array_filter(explode('|', $path))) : [];

        $position = array_search($categoryId, $pathIds, true);
        $idsBelow = $position === false ? [] : \array_slice($pathIds, $position + 1);

        return [...$idsBelow, $subcategory->getId()];
    }
}
