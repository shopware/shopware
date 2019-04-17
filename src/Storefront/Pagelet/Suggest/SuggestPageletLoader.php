<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Suggest;

use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Pagelet\Listing\ListingPageletLoader;
use Shopware\Storefront\Pagelet\Listing\Subscriber\SearchTermSubscriber;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class SuggestPageletLoader implements PageLoaderInterface
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ListingPageletLoader|PageLoaderInterface
     */
    private $listingPageletLoader;

    public function __construct(EventDispatcherInterface $eventDispatcher, PageLoaderInterface $listingPageletLoader)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->listingPageletLoader = $listingPageletLoader;
    }

    public function load(Request $request, SalesChannelContext $context): SuggestPagelet
    {
        $request->request->set('product-min-visibility', ProductVisibilityDefinition::VISIBILITY_SEARCH);

        $page = new SuggestPagelet(
            $this->listingPageletLoader->load($request, $context),
            $request->query->get(SearchTermSubscriber::TERM_PARAMETER)
        );

        $this->eventDispatcher->dispatch(
            SuggestPageletLoadedEvent::NAME,
            new SuggestPageletLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
