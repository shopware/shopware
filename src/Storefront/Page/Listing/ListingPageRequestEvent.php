<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Listing;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Storefront\Pagelet\ContentHeader\ContentHeaderPageletRequestEvent;
use Shopware\Storefront\Pagelet\Listing\ListingPageletRequestEvent;
use Shopware\Storefront\Pagelet\NavigationSidebar\NavigationSidebarPageletRequestEvent;
use Symfony\Component\HttpFoundation\Request;

class ListingPageRequestEvent extends NestedEvent
{
    public const NAME = 'listing.page.request';

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var Request
     */
    protected $httpRequest;

    /**
     * @var ListingPageRequest
     */
    protected $pageRequest;

    public function __construct(Request $httpRequest, CheckoutContext $context, ListingPageRequest $pageRequest)
    {
        $this->context = $context;
        $this->httpRequest = $httpRequest;
        $this->pageRequest = $pageRequest;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection([
            new ContentHeaderPageletRequestEvent($this->httpRequest, $this->context, $this->pageRequest->getHeaderRequest()),
            new NavigationSidebarPageletRequestEvent($this->httpRequest, $this->context, $this->pageRequest->getNavigationSidebarRequest()),
            new ListingPageletRequestEvent($this->httpRequest, $this->context, $this->pageRequest->getListingRequest()),
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

    public function getHttpRequest(): Request
    {
        return $this->httpRequest;
    }

    public function getListingPageRequest(): ListingPageRequest
    {
        return $this->pageRequest;
    }
}
