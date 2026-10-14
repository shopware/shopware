<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('discovery')]
class ProductSeoCategoryResolver
{
    /**
     * @param SalesChannelRepository<CategoryCollection> $categoryRepository
     */
    public function __construct(
        private readonly SalesChannelRepository $categoryRepository,
        private readonly Connection $connection,
    ) {
    }

    /**
     * Resolves the SEO category of several products with at most three batch reads, including inherited
     * main categories and dynamic product groups. Sales-channel category loading applies extensions'
     * availability rules before a category is selected.
     *
     * @param iterable<ProductEntity> $products
     *
     * @return array<string, CategoryEntity> keyed by product id
     */
    public function resolve(iterable $products, SalesChannelContext $context): array
    {
        $pending = [];
        foreach ($products as $product) {
            $pending[$product->getId()] = $product;
        }

        $categories = [];
        $withCategories = array_filter($pending, static fn (ProductEntity $product) => ($product->getCategoryIds() ?? []) !== []);
        $mainCategoryIds = $this->loadMainCategoryIds($withCategories, $context);

        // Load the main and assigned categories together, preferring visible and deeper categories.
        $candidateIds = array_values(array_unique(array_merge(array_values($mainCategoryIds), ...array_map(
            static fn (ProductEntity $product) => $product->getCategoryIds() ?? [],
            array_values($withCategories)
        ))));
        $candidates = $candidateIds === []
            ? new CategoryCollection()
            : $this->loadSortedCategories(new Criteria($candidateIds), $context);

        foreach ($withCategories as $productId => $product) {
            $productCategoryIds = $product->getCategoryIds() ?? [];
            $mainCategory = $candidates->get($mainCategoryIds[$productId] ?? '');

            // A main category must still be assigned and available to the customer.
            $category = $mainCategory !== null && \in_array($mainCategory->getId(), $productCategoryIds, true)
                ? $mainCategory
                : $candidates->firstWhere(static fn (CategoryEntity $category) => \in_array($category->getId(), $productCategoryIds, true));

            if ($category !== null) {
                $categories[$productId] = $category;
            }

            unset($pending[$productId]);
        }

        // a product without assigned categories can still be part of a dynamic product group
        $inStreams = array_filter($pending, static fn (ProductEntity $product) => ($product->getStreamIds() ?? []) !== []);
        $streamIds = array_values(array_unique(array_merge(...array_map(
            static fn (ProductEntity $product) => $product->getStreamIds() ?? [],
            array_values($inStreams)
        ))));

        if ($streamIds !== []) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsAnyFilter('productStreamId', $streamIds));
            $criteria->addFilter(new EqualsFilter('productAssignmentType', CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT_STREAM));
            $streamCategories = $this->loadSortedCategories($criteria, $context);

            foreach ($inStreams as $productId => $product) {
                $category = $streamCategories->firstWhere(static fn (CategoryEntity $category) => \in_array($category->getProductStreamId(), $product->getStreamIds() ?? [], true));

                if ($category !== null) {
                    $categories[$productId] = $category;
                }
            }
        }

        return $categories;
    }

    /**
     * The main category ids of the sales channel for several products in one read. A variant without
     * its own main category inherits the one of its parent, as the product entity does.
     *
     * @param array<string, ProductEntity> $products
     *
     * @return array<string, string> the main category id per product id
     */
    private function loadMainCategoryIds(array $products, SalesChannelContext $context): array
    {
        if ($products === []) {
            return [];
        }

        $lookupIds = [];
        foreach ($products as $product) {
            $lookupIds[] = $product->getId();
            if ($product->getParentId() !== null) {
                $lookupIds[] = $product->getParentId();
            }
        }

        /** @var array<string, string> $mainCategoryIds */
        $mainCategoryIds = $this->connection->fetchAllKeyValue(
            'SELECT LOWER(HEX(product_id)), LOWER(HEX(category_id))
             FROM main_category
             WHERE sales_channel_id = :salesChannelId
               AND product_id IN (:productIds)
               AND product_version_id = :versionId',
            [
                'salesChannelId' => Uuid::fromHexToBytes($context->getSalesChannelId()),
                'productIds' => Uuid::fromHexToBytesList(array_values(array_unique($lookupIds))),
                'versionId' => Uuid::fromHexToBytes($context->getContext()->getVersionId()),
            ],
            ['productIds' => ArrayParameterType::BINARY]
        );

        $result = [];
        foreach ($products as $productId => $product) {
            $categoryId = $mainCategoryIds[$productId] ?? $mainCategoryIds[$product->getParentId() ?? ''] ?? null;

            if ($categoryId !== null) {
                $result[$productId] = $categoryId;
            }
        }

        return $result;
    }

    /**
     * Categories the customer may see, sorted like the product detail breadcrumb.
     */
    private function loadSortedCategories(Criteria $criteria, SalesChannelContext $context): CategoryCollection
    {
        $criteria->setTitle('breadcrumb-builder::seo-categories');
        $salesChannel = $context->getSalesChannel();
        $rootIds = array_filter([
            $salesChannel->getNavigationCategoryId(),
            $salesChannel->getServiceCategoryId(),
            $salesChannel->getFooterCategoryId(),
        ], static fn (?string $id) => $id !== null && $id !== '');
        $criteria->addFilter(new AndFilter([
            new EqualsFilter('active', true),
            new OrFilter(array_map(static fn (string $id) => new ContainsFilter('path', '|' . $id . '|'), $rootIds)),
        ]));
        // categories hidden in the navigation must still yield a breadcrumb, but visible ones are preferred
        $criteria->addSorting(new FieldSorting('visible', FieldSorting::DESCENDING));
        $criteria->addSorting(new FieldSorting('level', FieldSorting::DESCENDING));

        // Subscribers may mark blocked categories inactive after the database read.
        return $this->categoryRepository->search($criteria, $context)->getEntities()->filterByProperty('active', true);
    }
}
