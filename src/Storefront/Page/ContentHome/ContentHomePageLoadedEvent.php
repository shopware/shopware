<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\ContentHome;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Storefront\Pagelet\ContentHeader\ContentHeaderPageletLoadedEvent;
use Shopware\Storefront\Pagelet\ContentHome\ContentHomePageletLoadedEvent;

class ContentHomePageLoadedEvent extends NestedEvent
{
    public const NAME = 'content-home.page.loaded';

    /**
     * @var ContentHomePageStruct
     */
    protected $page;

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var ContentHomePageRequest
     */
    protected $request;

    public function __construct(ContentHomePageStruct $page, CheckoutContext $context, ContentHomePageRequest $request)
    {
        $this->page = $page;
        $this->context = $context;
        $this->request = $request;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection([
            new ContentHeaderPageletLoadedEvent($this->page->getHeader(), $this->context, $this->request->getHeaderRequest()),
            new ContentHomePageletLoadedEvent($this->page->getContentHome(), $this->context, $this->request->getContentHomeRequest()),
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

    public function getPage(): ContentHomePageStruct
    {
        return $this->page;
    }

    public function getRequest(): ContentHomePageRequest
    {
        return $this->request;
    }
}
