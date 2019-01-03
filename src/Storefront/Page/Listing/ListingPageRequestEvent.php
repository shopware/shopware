<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Listing;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Symfony\Component\HttpFoundation\Request;

class ListingPageRequestEvent extends NestedEvent
{
    public const NAME = 'listing.page.request.event';

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var ListingPageRequest
     */
    protected $listingPageRequest;

    public function __construct(Request $request, CheckoutContext $context, ListingPageRequest $listingPageRequest)
    {
        $this->context = $context;
        $this->request = $request;
        $this->listingPageRequest = $listingPageRequest;
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

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getListingPageRequest(): ListingPageRequest
    {
        return $this->listingPageRequest;
    }
}
