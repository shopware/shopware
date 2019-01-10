<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountPaymentMethod;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Storefront\Pagelet\AccountPaymentMethod\AccountPaymentMethodPageletLoadedEvent;
use Shopware\Storefront\Pagelet\ContentHeader\ContentHeaderPageletLoadedEvent;

class AccountPaymentMethodPageLoadedEvent extends NestedEvent
{
    public const NAME = 'account-paymentmethod.page.loaded';

    /**
     * @var AccountPaymentMethodPageStruct
     */
    protected $page;

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var AccountPaymentMethodPageRequest
     */
    protected $request;

    public function __construct(AccountPaymentMethodPageStruct $page, CheckoutContext $context, AccountPaymentMethodPageRequest $request)
    {
        $this->page = $page;
        $this->context = $context;
        $this->request = $request;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection([
            new ContentHeaderPageletLoadedEvent($this->page->getHeader(), $this->context, $this->request->getHeaderRequest()),
            new AccountPaymentMethodPageletLoadedEvent($this->page->getAccountPaymentMethod(), $this->context, $this->request->getAccountPaymentMethodRequest()),
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

    public function getPage(): AccountPaymentMethodPageStruct
    {
        return $this->page;
    }

    public function getRequest(): AccountPaymentMethodPageRequest
    {
        return $this->request;
    }
}
