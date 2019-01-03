<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Listing;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Storefront\Pagelet\Listing\ListingPageletStruct;

class ListingPageLoadedEvent extends NestedEvent
{
    public const NAME = 'listing.page.loaded.event';

    /**
     * @var ListingPageletStruct
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

    public function __construct(ListingPageletStruct $page, CheckoutContext $context, ListingPageRequest $request)
    {
        $this->page = $page;
        $this->context = $context;
        $this->request = $request;
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

    public function getPage(): ListingPageletStruct
    {
        return $this->page;
    }

    public function getRequest(): ListingPageRequest
    {
        return $this->request;
    }
}
