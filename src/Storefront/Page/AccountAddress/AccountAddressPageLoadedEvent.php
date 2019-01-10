<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountAddress;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Storefront\Pagelet\AccountAddress\AccountAddressPageletLoadedEvent;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoadedEvent;

class AccountAddressPageLoadedEvent extends NestedEvent
{
    public const NAME = 'account-address.pagelet.loaded';

    /**
     * @var AccountAddressPageStruct
     */
    protected $page;

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var AccountAddressPageRequest
     */
    protected $request;

    public function __construct(AccountAddressPageStruct $page, CheckoutContext $context, AccountAddressPageRequest $request)
    {
        $this->page = $page;
        $this->context = $context;
        $this->request = $request;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection([
            new HeaderPageletLoadedEvent($this->page->getHeader(), $this->context, $this->request->getHeaderRequest()),
            new AccountAddressPageletLoadedEvent($this->page->getAccountAddress(), $this->context, $this->request->getAddressRequest())
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

    public function getPage(): AccountAddressPageStruct
    {
        return $this->page;
    }

    public function getRequest(): AccountAddressPageRequest
    {
        return $this->request;
    }
}
