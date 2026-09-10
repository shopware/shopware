<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cart;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Resolves the category path of a product as a list of names, ordered from the top level down.
 *
 * Only data that {@see ProductGateway} already loads is used, so resolving a path costs no
 * additional queries.
 *
 * @internal
 */
#[Package('inventory')]
class ProductCategoryPathResolver
{
    /**
     * The product's `categoryIds` cannot be used for this, because for a product assigned to
     * multiple branches it is a union of those branches and therefore not a path. A single
     * category is selected instead and its stored breadcrumb is used, which is always a path.
     *
     * @return list<string>
     */
    public function getPath(SalesChannelProductEntity $product, SalesChannelContext $context): array
    {
        $category = $this->getSeoCategory($product, $context);

        if ($category === null) {
            return [];
        }

        $breadcrumb = $category->getPlainBreadcrumb();
        $ids = array_keys($breadcrumb);

        foreach ($this->getSalesChannelEntryPoints($context) as $entryPoint) {
            $position = array_search($entryPoint, $ids, true);

            if ($position !== false) {
                return array_values(\array_slice($breadcrumb, $position + 1));
            }
        }

        return array_values($breadcrumb);
    }

    /**
     * Mirrors the category selection of `CategoryBreadcrumbBuilder::getProductSeoCategory()` so
     * the reported path matches the breadcrumb the storefront shows: the sales channel main category
     * when one is assigned, otherwise the deepest assigned category. That service is not reused here
     * because it queries per product whenever a product has no main category.
     */
    private function getSeoCategory(SalesChannelProductEntity $product, SalesChannelContext $context): ?CategoryEntity
    {
        $categoryIds = $product->getCategoryIds() ?? [];

        $mainCategory = $product->getMainCategories()
            ?->filterBySalesChannelId($context->getSalesChannelId())
            ->first()
            ?->getCategory();

        if ($mainCategory !== null
            && \in_array($mainCategory->getId(), $categoryIds, true)
            && $this->isCategoryVisible($mainCategory, $context)
        ) {
            return $mainCategory;
        }

        $deepest = null;
        foreach ($product->getCategories() ?? [] as $category) {
            if (!$this->isCategoryVisible($category, $context)) {
                continue;
            }

            if ($deepest === null || $category->getLevel() > $deepest->getLevel()) {
                $deepest = $category;
            }
        }

        return $deepest;
    }

    private function isCategoryVisible(CategoryEntity $category, SalesChannelContext $context): bool
    {
        if (!$category->getActive() || !$category->getVisible()) {
            return false;
        }

        $path = \array_slice(explode('|', $category->getPath() ?? ''), 1, -1);

        return array_intersect($path, $this->getSalesChannelEntryPoints($context)) !== [];
    }

    /**
     * @return list<string>
     */
    private function getSalesChannelEntryPoints(SalesChannelContext $context): array
    {
        $salesChannel = $context->getSalesChannel();

        return array_values(array_filter([
            $salesChannel->getNavigationCategoryId(),
            $salesChannel->getServiceCategoryId(),
            $salesChannel->getFooterCategoryId(),
        ]));
    }
}
