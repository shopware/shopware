<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Listing;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\Storefront\StorefrontProductRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Storefront\Event\ListingEvents;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ListingPageletLoader
{
    /**
     * @var StorefrontProductRepository
     */
    private $productRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
<<<<<<< HEAD:src/Storefront/Pagelet/Listing/ListingPageletLoader.php
     * @var ContainerInterface
     */
    private $container;
=======
     * @var RepositoryInterface
     */
    private $categoryRepository;
>>>>>>> NEXT-1454 - Implement category.display_nested_products:src/Storefront/Listing/PageLoader/ListingPageLoader.php

    public function __construct(
        StorefrontProductRepository $productRepository,
        RepositoryInterface $categoryRepository,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->productRepository = $productRepository;
        $this->categoryRepository = $categoryRepository;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param ContainerInterface|null $container
     */
    public function setContainer(ContainerInterface $container = null): void
    {
<<<<<<< HEAD:src/Storefront/Pagelet/Listing/ListingPageletLoader.php
        $this->container = $container;
    }

    /**
     * @param ListingPageletRequest $request
     * @param CheckoutContext       $context
     *
     * @return ListingPageletStruct
     */
    public function load(ListingPageletRequest $request, CheckoutContext $context): ListingPageletStruct
    {
        /** @var ListingPageletRequest $request */
=======
        $category = $this->categoryRepository
            ->read(new ReadCriteria([$request->getNavigationId()]), $context->getContext())
            ->get($request->getNavigationId());

>>>>>>> NEXT-1454 - Implement category.display_nested_products:src/Storefront/Listing/PageLoader/ListingPageLoader.php
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.active', true));

        /** @var CategoryEntity $category */
        if ($category->getDisplayNestedProducts()) {
            $criteria->addFilter(new EqualsFilter('product.categoriesRo.id', $request->getNavigationId()));
        } else {
            $criteria->addFilter(new EqualsFilter('product.categories.id', $request->getNavigationId()));
        }

        $this->eventDispatcher->dispatch(
            ListingEvents::CRITERIA_CREATED,
            new PageCriteriaCreatedEvent($criteria, $context, $request)
        );

        if (!$request->loadAggregations()) {
            $criteria->resetAggregations();
        }

        $products = $this->productRepository->search($criteria, $context);

        $page = new ListingPageletStruct();
        $page->setNavigationId($request->getNavigationId());
        $page->setProducts($products);
        $page->setCriteria($criteria);

        $page->setShowListing(true);
        $page->setProductBoxLayout('basic');

        return $page;
    }
}
