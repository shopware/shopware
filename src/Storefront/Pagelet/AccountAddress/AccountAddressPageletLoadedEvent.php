<?php

namespace Shopware\Storefront\Pagelet\AccountAddress;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Storefront\Page\AccountAddress\AccountAddressPageletStruct;

class AccountAddressPageletLoadedEvent extends NestedEvent
{
    public const NAME = 'account-address.pagelet.loaded';

    /**
     * @var AccountAddressPageletStruct
     */
    protected $pagelet;

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var AddressPageletRequest
     */
    protected $request;

    public function __construct(
        AccountAddressPageletStruct $pagelet,
        CheckoutContext $context,
        AddressPageletRequest $request
    ) {
        $this->pagelet = $pagelet;
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

    public function getPagelet(): AccountAddressPageletStruct
    {
        return $this->pagelet;
    }

    public function getRequest(): AddressPageletRequest
    {
        return $this->request;
    }
}