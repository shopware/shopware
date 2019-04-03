<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Search;

use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Framework\Page\PageWithHeaderLoader;
use Shopware\Storefront\Pagelet\Listing\ListingPageletLoader;
use Shopware\Storefront\Pagelet\Listing\Subscriber\SearchTermSubscriber;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SearchPageLoader implements PageLoaderInterface
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var PageWithHeaderLoader|PageLoaderInterface
     */
    private $pageWithHeaderLoader;

    /**
     * @var ListingPageletLoader|PageLoaderInterface
     */
    private $listingPageletLoader;

    public function __construct(
        PageLoaderInterface $pageWithHeaderLoader,
        PageLoaderInterface $listingPageletLoader,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->pageWithHeaderLoader = $pageWithHeaderLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->listingPageletLoader = $listingPageletLoader;
    }

    public function load(InternalRequest $request, SalesChannelContext $context): SearchPage
    {
        $page = $this->pageWithHeaderLoader->load($request, $context);

        $page = SearchPage::createFrom($page);

        $request->addParam('product-min-visibility', ProductVisibilityDefinition::VISIBILITY_SEARCH);

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
