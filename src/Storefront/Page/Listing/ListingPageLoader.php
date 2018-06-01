<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Listing;

use Shopware\Checkout\CustomerContext;
use Shopware\Content\Product\StorefrontProductRepository;
use Shopware\Framework\ORM\Search\Criteria;
use Shopware\Framework\ORM\Search\Query\TermQuery;
use Shopware\Storefront\Event\ListingEvents;
use Shopware\Storefront\Event\ListingPageLoadedEvent;
use Shopware\Storefront\Event\PageCriteriaCreatedEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ListingPageLoader
{
    /**
     * @var \Shopware\Content\Product\StorefrontProductRepository
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

    public function load(ListingPageRequest $request, CustomerContext $context): ListingPageStruct
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('product.active', 1));
        $criteria->addFilter(new TermQuery('product.categoriesRo.id', $request->getNavigationId()));

        $this->eventDispatcher->dispatch(
            ListingEvents::PAGE_CRITERIA_CREATED_EVENT,
            new PageCriteriaCreatedEvent($criteria, $context, $request)
        );

        if (!$request->loadAggregations()) {
            $criteria->setAggregations([]);
        }

        $products = $this->productRepository->search($criteria, $context);

        $page = new ListingPageStruct($request->getNavigationId(), $products, $criteria);

        $page->setShowListing(true);
        $page->setProductBoxLayout('basic');

        $this->eventDispatcher->dispatch(
            ListingEvents::LISTING_PAGE_LOADED_EVENT,
            new ListingPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
