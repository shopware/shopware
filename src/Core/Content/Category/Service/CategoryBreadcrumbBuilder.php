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
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class CategoryBreadcrumbBuilder
{
    /**
     * @var EntityRepositoryInterface|null
     */
    private $categoryRepository;

    /**
     * @deprecated tag:v6.4.0.0 - EntityRepositoryInterface will be required
     */
    public function __construct(?EntityRepositoryInterface $categoryRepository = null)
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

        $keys = array_keys($categoryBreadcrumb);

        foreach ($entryPoints as $entryPoint) {
            if ($entryPoint === null) {
                continue;
            }

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
        if ($this->categoryRepository === null) {
            return null;
        }

        if ($product->getCategoryTree() === null || \count($product->getCategoryTree()) === 0) {
            return null;
        }

        $category = $this->getMainCategory($product, $context);

        if ($category !== null) {
            return $category;
        }

        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('products.id', $product->getId()));
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new ContainsFilter('path', '|' . $context->getSalesChannel()->getNavigationCategoryId() . '|'),
            new ContainsFilter('path', '|' . $context->getSalesChannel()->getServiceCategoryId() . '|'),
            new ContainsFilter('path', '|' . $context->getSalesChannel()->getFooterCategoryId() . '|'),
        ]));

        $categories = $this->categoryRepository->search($criteria, $context->getContext());

        if ($categories->count() > 0) {
            return $categories->first();
        }

        return null;
    }

    private function getMainCategory(ProductEntity $product, SalesChannelContext $context): ?CategoryEntity
    {
        if ($this->categoryRepository === null) {
            return null;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('mainCategories.productId', $product->getId()));
        $criteria->addFilter(new EqualsFilter('mainCategories.salesChannelId', $context->getSalesChannel()->getId()));
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new ContainsFilter('path', '|' . $context->getSalesChannel()->getNavigationCategoryId() . '|'),
            new ContainsFilter('path', '|' . $context->getSalesChannel()->getServiceCategoryId() . '|'),
            new ContainsFilter('path', '|' . $context->getSalesChannel()->getFooterCategoryId() . '|'),
        ]));

        $categories = $this->categoryRepository->search($criteria, $context->getContext())->getEntities();
        if ($categories->count() > 0) {
            $firstCategory = $categories->first();

            return $firstCategory instanceof MainCategoryEntity ? $firstCategory->getCategory() : $firstCategory;
        }

        return null;
    }
}
