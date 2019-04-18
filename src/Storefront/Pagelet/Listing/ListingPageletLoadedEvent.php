<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Listing;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\StorefrontSearchResult;
use Symfony\Component\HttpFoundation\Request;

class ListingPageletLoadedEvent extends NestedEvent
{
    public const NAME = 'listing.pagelet.loaded.event';

    /**
     * @var StorefrontSearchResult
     */
    protected $searchResult;

    /**
     * @var SalesChannelContext
     */
    protected $context;

    /**
     * @var Request
     */
    protected $request;

    public function __construct(StorefrontSearchResult $searchResult, SalesChannelContext $context, Request $request)
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

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function getSearchResult(): StorefrontSearchResult
    {
        return $this->searchResult;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}
