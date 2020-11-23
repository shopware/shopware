<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Service;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Event\SalesChannelEntryPointsEvent;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Seo\MainCategory\MainCategoryEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CategoryBreadcrumbBuilder
{
    /**
     * @var EntityRepositoryInterface|null
     */
    private $categoryRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * @deprecated tag:v6.4.0.0 - EntityRepositoryInterface will be required
     */
    public function __construct(
        EventDispatcherInterface $dispatcher,
        ?EntityRepositoryInterface $categoryRepository = null
    ) {
        $this->dispatcher = $dispatcher;
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

        $context = Context::createDefaultContext();
        if ($salesChannel) {
            $event = SalesChannelEntryPointsEvent::forSalesChannel(
                $salesChannel,
                $context
            );

            if ($navigationCategoryId !== null) {
                $event->addId('navigation-category-id', $navigationCategoryId);
            }
        } else {
            $event = new SalesChannelEntryPointsEvent($context, [$navigationCategoryId]);
        }

        $this->dispatcher->dispatch($event);

        $entryPoints = array_filter(array_values($event->getNavigationIds()));

        $keys = array_keys($categoryBreadcrumb);

        foreach ($entryPoints as $entryPoint) {
            // Check where this category is located in relation to the navigation entry point of the sales channel
            $pos = array_search($entryPoint, $keys, true);

            if ($pos !== false) {
                // Remove all breadcrumbs preceding the navigation category
                return array_slice($categoryBreadcrumb, $pos + 1);
            }
        }

        return $categoryBreadcrumb;
    }

    public function getProductSeoCategory(ProductEntity $product, SalesChannelContext $context): ?CategoryEntity
    {
        if ($this->categoryRepository === null) {
            return null;
        }

        if ($product->getCategoryTree() === null || count($product->getCategoryTree()) === 0) {
            return null;
        }

        $category = $this->getMainCategory($product, $context);

        if ($category !== null) {
            return $category;
        }

        $event = SalesChannelEntryPointsEvent::forSalesChannelContext($context);
        $this->dispatcher->dispatch($event);

        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('products.id', $product->getId()));
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, array_map(function ($navigationId) {
            return new ContainsFilter('path', '|' . $navigationId . '|');
        }, $event->getNavigationIds())));

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

        $event = SalesChannelEntryPointsEvent::forSalesChannelContext($context);
        $this->dispatcher->dispatch($event);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('mainCategories.productId', $product->getId()));
        $criteria->addFilter(new EqualsFilter('mainCategories.salesChannelId', $context->getSalesChannel()->getId()));
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, array_map(function ($navigationId) {
            return new ContainsFilter('path', '|' . $navigationId . '|');
        }, $event->getNavigationIds())));

        $categories = $this->categoryRepository->search($criteria, $context->getContext())->getEntities();
        if ($categories->count() > 0) {
            $firstCategory = $categories->first();

            return $firstCategory instanceof MainCategoryEntity ? $firstCategory->getCategory() : $firstCategory;
        }

        return null;
    }
}
