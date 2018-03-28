<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Listing;

use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Storefront\Event\ListingEvents;
use Shopware\Storefront\Event\ListingPageLoadedEvent;
use Shopware\Storefront\Event\PageCriteriaCreatedEvent;
use Shopware\StorefrontApi\Product\StorefrontProductRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

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

    public function load(string $categoryId, Request $request, StorefrontContext $context, bool $loadAggregations = true): ListingPageStruct
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('product.active', 1));
        $criteria->addFilter(new TermQuery('product.categoriesRo.id', $categoryId));

        $this->eventDispatcher->dispatch(
            ListingEvents::PAGE_CRITERIA_CREATED_EVENT,
            new PageCriteriaCreatedEvent($criteria, $context, $request)
        );

        if (!$loadAggregations) {
            $criteria->setAggregations([]);
        }

        $products = $this->productRepository->search($criteria, $context);

        $page = new ListingPageStruct($products, $criteria);

        $page->setShowListing(true);
        $page->setProductBoxLayout('basic');

        $this->eventDispatcher->dispatch(
            ListingEvents::LISTING_PAGE_LOADED_EVENT,
            new ListingPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
