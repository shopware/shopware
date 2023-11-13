<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Service;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Seo\MainCategory\MainCategoryEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

#[Package('inventory')]
class CategoryBreadcrumbBuilder
{
    /**
     * @internal
     */
    public function __construct(private readonly EntityRepository $categoryRepository)
    {
    }

    /**
     * @return array<mixed>|null
     */
    public function build(CategoryEntity $category, ?SalesChannelEntity $salesChannel = null, ?string $navigationCategoryId = null): ?array
    {
        $categoryBreadcrumb = $category->getPlainBreadcrumb();

        // If the current SalesChannel is null ( which refers to the default template SalesChannel) or
        // this category has no root, we return the full breadcrumb
        if ($salesChannel === null && $navigationCategoryId === null) {
            return $categoryBreadcrumb;
        }

        $entryPoints = [
            $navigationCategoryId,
        ];

        if ($salesChannel !== null) {
            $entryPoints[] = $salesChannel->getNavigationCategoryId();
            $entryPoints[] = $salesChannel->getServiceCategoryId();
            $entryPoints[] = $salesChannel->getFooterCategoryId();
        }

        $entryPoints = array_filter($entryPoints);

        $keys = array_keys($categoryBreadcrumb);

        foreach ($entryPoints as $entryPoint) {
            // Check where this category is located in relation to the navigation entry point of the sales channel
            $pos = array_search($entryPoint, $keys, true);

            if ($pos !== false) {
                // Remove all breadcrumbs preceding the navigation category
                return \array_slice($categoryBreadcrumb, $pos + 1);
            }
        }

        return $categoryBreadcrumb;
    }

    public function getProductSeoCategory(ProductEntity $product, SalesChannelContext $context): ?CategoryEntity
    {
        $category = $this->getMainCategory($product, $context);

        if ($category !== null) {
            return $category;
        }

        $categoryIds = $product->getCategoryIds() ?? [];
        $productStreamIds = $product->getStreamIds() ?? [];

        if (empty($productStreamIds) && empty($categoryIds)) {
            return null;
        }

        $criteria = new Criteria();
        $criteria->setTitle('breadcrumb-builder');
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('active', true));

        if (!empty($categoryIds)) {
            $criteria->setIds($categoryIds);
        } else {
            $criteria->addFilter(new EqualsAnyFilter('productStream.id', $productStreamIds));
            $criteria->addFilter(new EqualsFilter('productAssignmentType', CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT_STREAM));
        }

        $criteria->addFilter($this->getSalesChannelFilter($context));

        $categories = $this->categoryRepository->search($criteria, $context->getContext());

        if ($categories->count() > 0) {
            /** @var CategoryEntity|null $category */
            $category = $categories->first();

            return $category;
        }

        return null;
    }

    private function getSalesChannelFilter(SalesChannelContext $context): MultiFilter
    {
        $ids = array_filter([
            $context->getSalesChannel()->getNavigationCategoryId(),
            $context->getSalesChannel()->getServiceCategoryId(),
            $context->getSalesChannel()->getFooterCategoryId(),
        ]);

        return new OrFilter(array_map(static fn (string $id) => new ContainsFilter('path', '|' . $id . '|'), $ids));
    }

    private function getMainCategory(ProductEntity $product, SalesChannelContext $context): ?CategoryEntity
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->setTitle('breadcrumb-builder::main-category');

        if (($product->getMainCategories() === null || $product->getMainCategories()->count() <= 0) && $product->getParentId() !== null) {
            $criteria->addFilter($this->getMainCategoryFilter($product->getParentId(), $context));
        } else {
            $criteria->addFilter($this->getMainCategoryFilter($product->getId(), $context));
        }

        $categories = $this->categoryRepository->search($criteria, $context->getContext())->getEntities();
        if ($categories->count() <= 0) {
            return null;
        }

        $firstCategory = $categories->first();

        /** @var CategoryEntity|null $entity */
        $entity = $firstCategory instanceof MainCategoryEntity ? $firstCategory->getCategory() : $firstCategory;

        return $product->getCategoryIds() !== null && $entity !== null && \in_array($entity->getId(), $product->getCategoryIds(), true) ? $entity : null;
    }

    private function getMainCategoryFilter(string $productId, SalesChannelContext $context): AndFilter
    {
        return new AndFilter([
            new EqualsFilter('mainCategories.productId', $productId),
            new EqualsFilter('mainCategories.salesChannelId', $context->getSalesChannelId()),
            $this->getSalesChannelFilter($context),
        ]);
    }
}
