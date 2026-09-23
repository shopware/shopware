<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cart;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\Events\ProductGatewayCriteriaEvent;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('inventory')]
class ProductGateway implements ProductGatewayInterface
{
    /**
     * @internal
     *
     * @param SalesChannelRepository<ProductCollection> $repository
     * @param EntityRepository<CategoryCollection>|null $categoryRepository optional, so a service
     *                                                                      definition that passes the
     *                                                                      two previous arguments keeps
     *                                                                      working; without it, products
     *                                                                      that are only assigned through
     *                                                                      a dynamic product group report
     *                                                                      no category path
     */
    public function __construct(
        private readonly SalesChannelRepository $repository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ?EntityRepository $categoryRepository = null,
    ) {
    }

    /**
     * @param list<string> $ids
     */
    public function get(array $ids, SalesChannelContext $context): ProductCollection
    {
        $criteria = new Criteria($ids);
        $criteria->setTitle('cart::products');
        $criteria->addAssociation('cover.media');
        $criteria->addAssociation('options.group');
        $criteria->addAssociation('featureSet');
        $criteria->addAssociation('properties.group');
        $criteria->addAssociation('manufacturer');

        // The category path of a line item is resolved from these two associations, see
        // ProductCategoryPathResolver. A cart is recalculated on almost every storefront request,
        // so both are narrowed to the rows the resolver can use at all: an inactive category is
        // never part of a path, and a main category of another sales channel is never selected.
        // Categories hidden from the navigation stay, because the storefront breadcrumb still
        // falls back to them.
        $criteria->addAssociation('categories');
        $criteria->getAssociation('categories')
            ->addFilter(new EqualsFilter('active', true));

        $criteria->addAssociation('mainCategories.category');
        $criteria->getAssociation('mainCategories')
            ->addFilter(new EqualsFilter('salesChannelId', $context->getSalesChannelId()));

        $this->eventDispatcher->dispatch(
            new ProductGatewayCriteriaEvent($ids, $criteria, $context)
        );

        $products = $this->repository->search($criteria, $context)->getEntities();

        $this->addStreamCategories($products, $context);

        return $products;
    }

    /**
     * A product that is only assigned to categories through a dynamic product group has no direct
     * category assignment, so the `categories` association is empty. The storefront breadcrumb
     * falls back to the categories that list the product through one of its streams, see
     * `CategoryBreadcrumbBuilder::getProductSeoCategory()`, and so does the category path.
     *
     * All such products of a cart are resolved with a single query, and only when at least one of
     * them exists, so a cart without them costs nothing.
     */
    private function addStreamCategories(ProductCollection $products, SalesChannelContext $context): void
    {
        if ($this->categoryRepository === null) {
            return;
        }

        $streamIds = [];
        foreach ($products as $product) {
            if (($product->getCategoryIds() ?? []) === []) {
                $streamIds = [...$streamIds, ...($product->getStreamIds() ?? [])];
            }
        }

        if ($streamIds === []) {
            return;
        }

        $criteria = new Criteria();
        $criteria->setTitle('cart::products::stream-categories');
        $criteria->addFilter(
            new EqualsAnyFilter('productStreamId', array_values(array_unique($streamIds))),
            new EqualsFilter('productAssignmentType', CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT_STREAM),
            new EqualsFilter('active', true),
        );

        $categories = $this->categoryRepository->search($criteria, $context->getContext())->getEntities();

        foreach ($products as $product) {
            if (($product->getCategoryIds() ?? []) !== []) {
                continue;
            }

            $productStreamIds = $product->getStreamIds() ?? [];

            $product->addExtension(
                ProductCategoryPathResolver::STREAM_CATEGORIES_EXTENSION,
                $categories->filter(static fn ($category) => \in_array($category->getProductStreamId(), $productStreamIds, true))
            );
        }
    }
}
