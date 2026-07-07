<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Extension\ProductListingCriteriaExtension;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\ProductStream\Service\AbstractProductStreamBuilder;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
#[Package('inventory')]
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
        $criteria->addFilter(
            new ProductAvailableFilter($context->getSalesChannelId(), ProductVisibilityDefinition::VISIBILITY_ALL)
        );
        $criteria->setTitle('product-listing-route::loading');

        $categories = $this->loadCategories($categoryId, $context);
        $category = $categories->get($categoryId);
        if (!$category) {
            throw ProductException::categoryNotFound($categoryId);
        }

        $this->addCacheTags($categories);

        $criteria = $this->extensions->publish(
            name: ProductListingCriteriaExtension::NAME,
            extension: new ProductListingCriteriaExtension($criteria, $context, $categoryId),
            function: function ($criteria, $context, $categoryId) use ($categories): Criteria {
                $this->extendCriteria($context, $criteria, $categories, $categoryId);

                return $criteria;
            }
        );

        $entities = $this->listingLoader->load($criteria, $context);

        $result = ProductListingResult::createFrom($entities);
        $result->addState(...$entities->getStates());

        $result->setStreamId($category->get('productStreamId'));

        return new ProductListingRouteResponse($result);
    }

    /**
     * @return EntityCollection<PartialEntity>
     */
    private function loadCategories(string $categoryId, SalesChannelContext $context): EntityCollection
    {
        $criteria = new Criteria();
        $criteria->setTitle('product-listing-route::category-loading');
        $criteria->addFields(['productAssignmentType', 'productStreamId']);
        $criteria->addFilter(new OrFilter([
            new EqualsFilter('id', $categoryId),
            new ContainsFilter('path', '|' . $categoryId . '|'),
        ]));

        return $this->categoryRepository->search($criteria, $context->getContext())->getEntities();
    }

    /**
     * @param EntityCollection<PartialEntity> $categories
     */
    private function extendCriteria(SalesChannelContext $salesChannelContext, Criteria $criteria, EntityCollection $categories, string $categoryId): void
    {
        if ($categories->get($categoryId) === null) {
            return;
        }

        $filters = [];
        $manualCategoryIds = [];

        foreach ($categories as $category) {
            if (!$this->isProductStreamCategory($category)) {
                // a manual category contributes the products assigned within its own subtree (via
                // categoriesRo). A stream category is never collected here, so its own directly-assigned
                // products stay excluded - only its stream defines what it shows.
                $manualCategoryIds[] = $category->getId();

                continue;
            }

            $streamFilters = $this->getProductStreamFilters($category, $salesChannelContext);

            if ($streamFilters !== []) {
                $filters[] = $this->groupFilters($streamFilters);
            }
        }

        if ($manualCategoryIds !== []) {
            $filters[] = new EqualsAnyFilter('product.categoriesRo.id', $manualCategoryIds);
        }

        if ($filters === []) {
            return;
        }

        $criteria->addFilter(\count($filters) === 1 ? $filters[0] : new OrFilter($filters));
    }

    /**
     * @return list<Filter>
     */
    private function getProductStreamFilters(PartialEntity $category, SalesChannelContext $salesChannelContext): array
    {
        if (!$this->isProductStreamCategory($category)) {
            return [];
        }

        $productStreamId = $category->get('productStreamId');
        \assert(\is_string($productStreamId));

        $productStreamBuilder = $this->productStreamBuilder;
        if ($productStreamBuilder instanceof AbstractProductStreamBuilder) {
            $streamCriteria = new Criteria();
            $productStreamBuilder->enrichCriteria($streamCriteria, $productStreamId, $salesChannelContext->getContext());

            return array_values($streamCriteria->getFilters());
        }

        return array_values($productStreamBuilder->buildFilters(
            $productStreamId,
            $salesChannelContext->getContext()
        ));
    }

    private function isProductStreamCategory(PartialEntity $category): bool
    {
        return $category->get('productAssignmentType') === CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT_STREAM
            && \is_string($category->get('productStreamId'));
    }

    /**
     * @param list<Filter> $filters
     */
    private function groupFilters(array $filters): Filter
    {
        if (\count($filters) === 1) {
            return $filters[0];
        }

        return new AndFilter($filters);
    }

    /**
     * @param EntityCollection<PartialEntity> $categories
     */
    private function addCacheTags(EntityCollection $categories): void
    {
        $tags = [];

        foreach ($categories as $category) {
            $tags[] = self::buildName($category->getId());

            if (
                $category->get('productAssignmentType') === CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT_STREAM
                && $category->get('productStreamId') !== null
            ) {
                $tags[] = EntityCacheKeyGenerator::buildStreamTag($category->get('productStreamId'));
            }
        }

        $this->cacheTagCollector->addTag(...array_values(array_unique($tags)));
    }
}
