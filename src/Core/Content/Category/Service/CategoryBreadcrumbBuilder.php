<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Service;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Seo\MainCategory\MainCategoryEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class CategoryBreadcrumbBuilder
{
    private EntityRepositoryInterface $categoryRepository;

    public function __construct(EntityRepositoryInterface $categoryRepository)
    {
        $this->categoryRepository = $categoryRepository;
    }

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
        if ($product->getCategoryTree() === null || \count($product->getCategoryTree()) === 0) {
            return null;
        }

        $category = $this->getMainCategory($product, $context);

        if ($category !== null) {
            return $category;
        }

        $ids = $product->getCategoryIds();

        if (empty($ids)) {
            return null;
        }

        $criteria = new Criteria($ids);
        $criteria->setTitle('breadcrumb-builder');
        $criteria->setLimit(1);
        $criteria->addFilter($this->getSalesChannelFilter($context));

        $categories = $this->categoryRepository->search($criteria, $context->getContext());

        if ($categories->count() > 0) {
            return $categories->first();
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

        return new OrFilter(array_map(static function (string $id) {
            return new ContainsFilter('path', '|' . $id . '|');
        }, $ids));
    }

    private function getMainCategory(ProductEntity $product, SalesChannelContext $context): ?CategoryEntity
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->setTitle('breadcrumb-builder::main-category');
        $criteria->addFilter(new EqualsFilter('mainCategories.productId', $product->getId()));
        $criteria->addFilter(new EqualsFilter('mainCategories.salesChannelId', $context->getSalesChannel()->getId()));
        $criteria->addFilter($this->getSalesChannelFilter($context));

        $categories = $this->categoryRepository->search($criteria, $context->getContext())->getEntities();
        if ($categories->count() <= 0) {
            return null;
        }

        $firstCategory = $categories->first();

        return $firstCategory instanceof MainCategoryEntity ? $firstCategory->getCategory() : $firstCategory;
    }
}
