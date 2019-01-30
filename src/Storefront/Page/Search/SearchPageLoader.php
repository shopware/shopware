<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Search;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Storefront\Framework\Page\PageWithHeaderLoader;
use Shopware\Storefront\Pagelet\Listing\ListingPageletLoader;
use Shopware\Storefront\Pagelet\Listing\Subscriber\SearchTermSubscriber;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SearchPageLoader
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var PageWithHeaderLoader
     */
    private $pageWithHeaderLoader;

    /**
     * @var ListingPageletLoader
     */
    private $listingPageletLoader;

    public function __construct(
        PageWithHeaderLoader $pageWithHeaderLoader,
        ListingPageletLoader $listingPageletLoader,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->pageWithHeaderLoader = $pageWithHeaderLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->listingPageletLoader = $listingPageletLoader;
    }

    public function load(InternalRequest $request, CheckoutContext $context): SearchPage
    {
        $page = $this->pageWithHeaderLoader->load($request, $context);

        $page = SearchPage::createFrom($page);

        $page->setListing(
            $this->listingPageletLoader->load($request, $context)
        );

        $page->setSearchTerm(
            (string) $request->requireGet(SearchTermSubscriber::TERM_PARAMETER)
        );

        $this->eventDispatcher->dispatch(
            SearchPageLoadedEvent::NAME,
            new SearchPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
