<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Listing;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class ListingPageletLoadedEvent extends NestedEvent
{
    public const NAME = 'listing.pagelet.loaded.event';

    /**
     * @var ListingPageletStruct
     */
    protected $page;

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var ListingPageletRequest
     */
    protected $request;

    public function __construct(ListingPageletStruct $page, CheckoutContext $context, ListingPageletRequest $request)
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

    public function getRequest(): ListingPageletRequest
    {
        return $this->request;
    }
}
