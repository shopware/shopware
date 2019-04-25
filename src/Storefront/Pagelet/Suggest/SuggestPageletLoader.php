<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Suggest;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Pagelet\Listing\ListingPageletLoader;
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
        $page = new SuggestPagelet(
            $this->listingPageletLoader->load($request, $context),
            $request->query->get('search')
        );

        $this->eventDispatcher->dispatch(
            SuggestPageletLoadedEvent::NAME,
            new SuggestPageletLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
