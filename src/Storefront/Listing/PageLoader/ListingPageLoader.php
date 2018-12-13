<?php declare(strict_types=1);

namespace Shopware\Storefront\Listing\PageLoader;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Product\Storefront\StorefrontProductRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Storefront\Listing\Event\ListingEvents;
use Shopware\Storefront\Listing\Event\ListingPageLoadedEvent;
use Shopware\Storefront\Listing\Event\PageCriteriaCreatedEvent;
use Shopware\Storefront\Listing\Page\ListingPageRequest;
use Shopware\Storefront\Listing\Page\ListingPageStruct;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ListingPageLoader
{
    /**
     * @var StorefrontProductRepository
     */
    private $productRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        StorefrontProductRepository $productRepository,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->productRepository = $productRepository;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function load(ListingPageRequest $request, CheckoutContext $context): ListingPageStruct
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.active', true));
        $criteria->addFilter(new EqualsFilter('product.categoriesRo.id', $request->getNavigationId()));

        $this->eventDispatcher->dispatch(
            ListingEvents::CRITERIA_CREATED,
            new PageCriteriaCreatedEvent($criteria, $context, $request)
        );

        if (!$request->loadAggregations()) {
            $criteria->resetAggregations();
        }

        $products = $this->productRepository->search($criteria, $context);

        $page = new ListingPageStruct($request->getNavigationId(), $products, $criteria);

        $page->setShowListing(true);
        $page->setProductBoxLayout('basic');

        $this->eventDispatcher->dispatch(
            ListingEvents::LOADED,
            new ListingPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
