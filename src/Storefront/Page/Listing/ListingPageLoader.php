<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Listing;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Storefront\Pagelet\ContentHeader\ContentHeaderPageletLoader;
use Shopware\Storefront\Pagelet\Listing\ListingPageletLoader;
use Shopware\Storefront\Pagelet\NavigationSidebar\NavigationSidebarPageletLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ListingPageLoader
{
    /**
     * @var ListingPageletLoader
     */
    private $listingPageletLoader;

    /**
     * @var NavigationSidebarPageletLoader
     */
    private $navigationSidebarPageletLoader;

    /**
     * @var ContentHeaderPageletLoader
     */
    private $headerPageletLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        ListingPageletLoader $listingPageletLoader,
        NavigationSidebarPageletLoader $navigationSidebarPageletLoader,
        ContentHeaderPageletLoader $headerPageletLoader,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->listingPageletLoader = $listingPageletLoader;
        $this->navigationSidebarPageletLoader = $navigationSidebarPageletLoader;
        $this->headerPageletLoader = $headerPageletLoader;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function load(InternalRequest $request, CheckoutContext $context): ListingPageStruct
    {
        $page = new ListingPageStruct();
        $page->setListing(
            $this->listingPageletLoader->load($request, $context)
        );

        $page->setNavigationSidebar(
            $this->navigationSidebarPageletLoader->load($request, $context)
        );

        $page->setHeader(
            $this->headerPageletLoader->load($request, $context)
        );

        $this->eventDispatcher->dispatch(
            ListingPageLoadedEvent::NAME,
            new ListingPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
