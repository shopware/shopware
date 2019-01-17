<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Search;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Storefront\Pagelet\ContentHeader\ContentHeaderPageletLoader;
use Shopware\Storefront\Pagelet\Search\SearchPageletLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SearchPageLoader
{
    /**
     * @var SearchPageletLoader
     */
    private $searchPageletLoader;

    /**
     * @var ContentHeaderPageletLoader
     */
    private $headerPageletLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        SearchPageletLoader $searchPageletLoader,
        ContentHeaderPageletLoader $headerPageletLoader,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->searchPageletLoader = $searchPageletLoader;
        $this->headerPageletLoader = $headerPageletLoader;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function load(InternalRequest $request, CheckoutContext $context): SearchPageStruct
    {
        $page = new SearchPageStruct();
        $page->setSearch(
            $this->searchPageletLoader->load($request, $context)
        );

        $page->setHeader(
            $this->headerPageletLoader->load($request, $context)
        );

        $this->eventDispatcher->dispatch(
            SearchPageLoadedEvent::NAME,
            new SearchPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
