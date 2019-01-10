<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountOrder;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Storefront\Pagelet\AccountOrder\AccountOrderPageletLoadedEvent;
use Shopware\Storefront\Pagelet\ContentHeader\ContentHeaderPageletLoadedEvent;

class AccountOrderPageLoadedEvent extends NestedEvent
{
    public const NAME = 'account-order.page.loaded';

    /**
     * @var AccountOrderPageStruct
     */
    protected $page;

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var AccountOrderPageRequest
     */
    protected $request;

    public function __construct(AccountOrderPageStruct $page, CheckoutContext $context, AccountOrderPageRequest $request)
    {
        $this->page = $page;
        $this->context = $context;
        $this->request = $request;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection([
            new ContentHeaderPageletLoadedEvent($this->page->getHeader(), $this->context, $this->request->getHeaderRequest()),
            new AccountOrderPageletLoadedEvent($this->page->getAccountOrder(), $this->context, $this->request->getAccountOrderRequest()),
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

    public function getPage(): AccountOrderPageStruct
    {
        return $this->page;
    }

    public function getRequest(): AccountOrderPageRequest
    {
        return $this->request;
    }
}
