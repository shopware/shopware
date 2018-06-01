<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Framework\Context;
use Shopware\Checkout\CustomerContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Storefront\Page\Listing\ListingPageRequest;
use Symfony\Component\HttpFoundation\Request;

class TransformListingPageRequestEvent extends NestedEvent
{
    public const NAME = 'transform.listing.page.request.event';

    /**
     * @var CustomerContext
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

    public function __construct(Request $request, CustomerContext $context, ListingPageRequest $listingPageRequest)
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
        return $this->context->getApplicationContext();
    }

    public function getStorefrontContext(): CustomerContext
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
