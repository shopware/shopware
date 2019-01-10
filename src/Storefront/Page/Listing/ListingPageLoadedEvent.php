<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Listing;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Storefront\Pagelet\ContentHeader\ContentHeaderPageletLoadedEvent;
use Shopware\Storefront\Pagelet\Listing\ListingPageletLoadedEvent;

class ListingPageLoadedEvent extends NestedEvent
{
    public const NAME = 'listing.page.loaded';

    /**
     * @var ListingPageStruct
     */
    protected $page;

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var ListingPageRequest
     */
    protected $request;

    public function __construct(ListingPageStruct $page, CheckoutContext $context, ListingPageRequest $request)
    {
        $this->page = $page;
        $this->context = $context;
        $this->request = $request;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection([
            new ContentHeaderPageletLoadedEvent($this->page->getHeader(), $this->context, $this->request->getHeaderRequest()),
            new ListingPageletLoadedEvent($this->page->getListing(), $this->context, $this->request->getListingRequest()),
        ]);
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    public function getCheckoutContext(): CheckoutContext
    {
        return $this->context;
    }

    public function getPage(): ListingPageStruct
    {
        return $this->page;
    }

    public function getRequest(): ListingPageRequest
    {
        return $this->request;
    }
}
