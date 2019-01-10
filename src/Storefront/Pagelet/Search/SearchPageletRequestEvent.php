<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Search;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Storefront\Pagelet\Listing\ListingPageletRequestEventInterface;
use Shopware\Storefront\Pagelet\Listing\ListingPageletRequestInterface;
use Symfony\Component\HttpFoundation\Request;

class SearchPageletRequestEvent extends NestedEvent implements ListingPageletRequestEventInterface
{
    public const NAME = 'search.pagelet.request.event';

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var SearchPageletRequest
     */
    protected $searchPageletRequest;

    public function __construct(Request $request, CheckoutContext $context, SearchPageletRequest $searchPageletRequest)
    {
        $this->context = $context;
        $this->request = $request;
        $this->searchPageletRequest = $searchPageletRequest;
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

    public function getSearchPageletRequest(): SearchPageletRequest
    {
        return $this->searchPageletRequest;
    }

    public function getListingPageletRequest(): ListingPageletRequestInterface
    {
        return $this->searchPageletRequest;
    }
}
