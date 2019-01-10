<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Search;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Storefront\Pagelet\ContentHeader\ContentHeaderPageletLoadedEvent;
use Shopware\Storefront\Pagelet\Search\SearchPageletLoadedEvent;

class SearchPageLoadedEvent extends NestedEvent
{
    public const NAME = 'search.page.loaded';

    /**
     * @var SearchPageStruct
     */
    protected $page;

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var SearchPageRequest
     */
    protected $request;

    public function __construct(SearchPageStruct $page, CheckoutContext $context, SearchPageRequest $request)
    {
        $this->page = $page;
        $this->context = $context;
        $this->request = $request;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection([
            new ContentHeaderPageletLoadedEvent($this->page->getHeader(), $this->context, $this->request->getHeaderRequest()),
            new SearchPageletLoadedEvent($this->page->getSearch(), $this->context, $this->request->getSearchRequest()),
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

    public function getPage(): SearchPageStruct
    {
        return $this->page;
    }

    public function getRequest(): SearchPageRequest
    {
        return $this->request;
    }
}
