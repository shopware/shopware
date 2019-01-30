<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Listing;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Storefront\Framework\Page\StorefrontSearchResult;

class ListingPageletLoadedEvent extends NestedEvent
{
    public const NAME = 'listing.pagelet.loaded.event';

    /**
     * @var StorefrontSearchResult
     */
    protected $searchResult;

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var InternalRequest
     */
    protected $request;

    public function __construct(StorefrontSearchResult $searchResult, CheckoutContext $context, InternalRequest $request)
    {
        $this->searchResult = $searchResult;
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

    public function getSearchResult(): StorefrontSearchResult
    {
        return $this->searchResult;
    }

    public function getRequest(): InternalRequest
    {
        return $this->request;
    }
}
