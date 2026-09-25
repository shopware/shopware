<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cart;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Attaches the categories that list a product through a dynamic product group to every given product
 * without direct category assignment, as {@see ProductCategoryPathResolver::STREAM_CATEGORIES_EXTENSION}.
 *
 * Such a product has an empty `categories` association, while the storefront breadcrumb falls back
 * to these categories, see `CategoryBreadcrumbBuilder::getProductSeoCategory()`, and so does the
 * category path. All given products are resolved with a single query, and only when at least one of
 * them needs it.
 *
 * @internal
 */
#[Package('inventory')]
class ProductStreamCategoryLoader
{
    /**
     * @param EntityRepository<CategoryCollection> $categoryRepository
     */
    public function __construct(private readonly EntityRepository $categoryRepository)
    {
    }

    /**
     * @param iterable<ProductEntity> $products
     */
    public function load(iterable $products, SalesChannelContext $context): void
    {
        $pending = [];
        $streamIds = [];

        foreach ($products as $product) {
            if (($product->getCategoryIds() ?? []) !== [] || $product->hasExtension(ProductCategoryPathResolver::STREAM_CATEGORIES_EXTENSION)) {
                continue;
            }

            $pending[] = $product;
            $streamIds = [...$streamIds, ...($product->getStreamIds() ?? [])];
        }

        if ($streamIds === []) {
            return;
        }

        $criteria = new Criteria();
        $criteria->setTitle('product::stream-categories');
        $criteria->addFilter(
            new EqualsAnyFilter('productStreamId', array_values(array_unique($streamIds))),
            new EqualsFilter('productAssignmentType', CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT_STREAM),
            new EqualsFilter('active', true),
        );

        $categories = $this->categoryRepository->search($criteria, $context->getContext())->getEntities();

        foreach ($pending as $product) {
            $productStreamIds = $product->getStreamIds() ?? [];

            $product->addExtension(
                ProductCategoryPathResolver::STREAM_CATEGORIES_EXTENSION,
                $categories->filter(static fn ($category) => \in_array($category->getProductStreamId(), $productStreamIds, true))
            );
        }
    }
}
