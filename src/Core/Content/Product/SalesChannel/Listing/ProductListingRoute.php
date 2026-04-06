<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Extension\ProductListingCriteriaExtension;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
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
     * @param EntityRepository<CategoryCollection> $categoryRepository
     */
    public function __construct(
        private readonly ProductListingLoader $listingLoader,
        private readonly EntityRepository $categoryRepository,
        private readonly ProductStreamBuilderInterface $productStreamBuilder,
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

        $category = $this->loadCategory($categoryId, $context);
        $categories = [$category, ...$this->loadDescendantCategories($categoryId, $context)];

        $this->addCacheTags($categories);

        $criteria = $this->extensions->publish(
            name: ProductListingCriteriaExtension::NAME,
            extension: new ProductListingCriteriaExtension($criteria, $context, $categoryId),
            function: function ($criteria, $context, $categoryId) use ($categories): Criteria {
                $this->extendCriteria($context, $criteria, $categories);

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
     * @param list<PartialEntity> $categories
     */
    private function extendCriteria(SalesChannelContext $salesChannelContext, Criteria $criteria, array $categories): void
    {
        $filters = [];

        foreach ($categories as $category) {
            $filters[] = $this->buildCategoryFilter($category, $salesChannelContext);
        }

        if ($filters === []) {
            return;
        }

        if (\count($filters) === 1) {
            $criteria->addFilter($filters[0]);

            return;
        }

        $criteria->addFilter(new OrFilter($filters));
    }

    private function loadCategory(string $categoryId, SalesChannelContext $context): PartialEntity
    {
        $categoryCriteria = new Criteria([$categoryId]);
        $categoryCriteria->setTitle('product-listing-route::category-loading');
        $categoryCriteria->addFields(['productAssignmentType', 'productStreamId']);
        $categoryCriteria->setLimit(1);

        /** @var ?PartialEntity $category */
        $category = $this->categoryRepository->search($categoryCriteria, $context->getContext())->getEntities()->first();
        if (!$category) {
            throw ProductException::categoryNotFound($categoryId);
        }

        return $category;
    }

    /**
     * @return list<PartialEntity>
     */
    private function loadDescendantCategories(string $categoryId, SalesChannelContext $context): array
    {
        $criteria = new Criteria();
        $criteria->setTitle('product-listing-route::descendant-category-loading');
        $criteria->addFields(['productAssignmentType', 'productStreamId']);
        $criteria->addFilter(new ContainsFilter('path', '|' . $categoryId . '|'));

        return array_values(
            $this->categoryRepository->search($criteria, $context->getContext())->getEntities()->getElements()
        );
    }

    /**
     * @param list<PartialEntity> $categories
     */
    private function addCacheTags(array $categories): void
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

    private function buildCategoryFilter(PartialEntity $category, SalesChannelContext $salesChannelContext): Filter
    {
        if (
            $category->get('productAssignmentType') === CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT_STREAM
            && $category->get('productStreamId') !== null
        ) {
            $filters = $this->productStreamBuilder->buildFilters(
                $category->get('productStreamId'),
                $salesChannelContext->getContext()
            );

            if (\count($filters) === 1) {
                return $filters[0];
            }

            return new AndFilter($filters);
        }

        return new EqualsFilter('product.categories.id', $category->getId());
    }
}
