<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Analytics;

use Shopware\Core\Content\Category\Service\ProductSeoCategoryResolver;
use Shopware\Core\Content\Category\Util\CategoryBreadcrumbHelper;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Loads the category path analytics reports for products that are shown outside a category, such as
 * on the homepage or a landing page, where the page breadcrumb describes no category.
 *
 * The category is chosen by
 * {@see ProductSeoCategoryResolver::resolve()} for all products of a page together, and
 * the names come from the stored breadcrumb of that category, cut off at the sales channel entry point.
 *
 * @internal
 */
#[Package('discovery')]
class AnalyticsCategoryPathLoader
{
    public function __construct(private readonly ProductSeoCategoryResolver $categoryResolver)
    {
    }

    /**
     * @param list<ProductEntity> $products
     *
     * @return array<string, list<string>> the category names from the top level down, per product id
     */
    public function load(array $products, SalesChannelContext $context): array
    {
        if ($products === []) {
            return [];
        }

        $paths = [];

        foreach ($this->categoryResolver->resolve($products, $context) as $productId => $category) {
            $names = array_values(array_filter(
                CategoryBreadcrumbHelper::build($category, $context->getSalesChannel()) ?? [],
                static fn (mixed $name) => \is_string($name) && $name !== ''
            ));

            if ($names !== []) {
                $paths[$productId] = $names;
            }
        }

        return $paths;
    }
}
