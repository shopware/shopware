<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Search;

use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Framework\Page\PageWithHeaderLoader;
use Shopware\Storefront\Pagelet\Listing\ListingPageletLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

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

    public function load(Request $request, SalesChannelContext $context): SearchPage
    {
        $page = $this->pageWithHeaderLoader->load($request, $context);
        $page = SearchPage::createFrom($page);

        if (!$request->query->has('search')) {
            throw new MissingRequestParameterException('search');
        }

        $page->setListing(
            $this->listingPageletLoader->load($request, $context)
        );

        $page->setSearchTerm(
            (string) $request->query->get('search')
        );

        $this->eventDispatcher->dispatch(
            SearchPageLoadedEvent::NAME,
            new SearchPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
