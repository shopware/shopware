<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Listing;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Product\Storefront\StorefrontProductRepository;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Shopware\Storefront\Event\ListingEvents;
use Shopware\Storefront\Event\ListingPageLoadedEvent;
use Shopware\Storefront\Event\PageCriteriaCreatedEvent;
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
        $criteria->addFilter(new TermQuery('product.active', 1));
        $criteria->addFilter(new TermQuery('product.categoriesRo.id', $request->getNavigationId()));

        $this->eventDispatcher->dispatch(
            ListingEvents::CRITERIA_CREATED,
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
            ListingEvents::LOADED,
            new ListingPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
