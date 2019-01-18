<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Suggest;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Pagelet\Listing\ListingPageletLoader;
use Shopware\Storefront\Pagelet\Listing\Subscriber\SearchTermSubscriber;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SuggestPageletLoader implements PageLoaderInterface
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ListingPageletLoader
     */
    private $listingPageletLoader;

    public function __construct(EventDispatcherInterface $eventDispatcher, ListingPageletLoader $listingPageletLoader)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->listingPageletLoader = $listingPageletLoader;
    }

    public function load(InternalRequest $request, CheckoutContext $context): SuggestPagelet
    {
        $page = new SuggestPagelet(
            $this->listingPageletLoader->load($request, $context),
            $request->requireGet(SearchTermSubscriber::TERM_PARAMETER)
        );

        $this->eventDispatcher->dispatch(
            SuggestPageletLoadedEvent::NAME,
            new SuggestPageletLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
