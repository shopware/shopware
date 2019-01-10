<?php

namespace Shopware\Storefront\Pagelet\Header;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Storefront\Pagelet\CartInfo\CartInfoPageletLoadedEvent;
use Shopware\Storefront\Pagelet\Currency\CurrencyPageletLoadedEvent;
use Shopware\Storefront\Pagelet\Language\LanguagePageletLoadedEvent;
use Shopware\Storefront\Pagelet\Navigation\NavigationPageletLoadedEvent;
use Shopware\Storefront\Pagelet\Shopmenu\ShopmenuPageletLoadedEvent;

class HeaderPageletLoadedEvent extends NestedEvent
{
    public const NAME = 'account-address.pagelet.loaded';

    /**
     * @var HeaderPageletStruct
     */
    protected $pagelet;

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var HeaderPageletRequest
     */
    protected $request;

    public function __construct(HeaderPageletStruct $page, CheckoutContext $context, HeaderPageletRequest $request)
    {
        $this->pagelet = $page;
        $this->context = $context;
        $this->request = $request;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection([
            new NavigationPageletLoadedEvent($this->pagelet->getNavigation(), $this->context, $this->request->getNavigationRequest()),
            new CartInfoPageletLoadedEvent($this->pagelet->getCartInfo(), $this->context, $this->request->getCartInfoRequest()),
            new ShopmenuPageletLoadedEvent($this->pagelet->getShopmenu(), $this->context, $this->request->getShopmenuRequest()),
            new CurrencyPageletLoadedEvent($this->pagelet->getCurrency(), $this->context, $this->request->getCurrencyRequest()),
            new LanguagePageletLoadedEvent($this->pagelet->getLanguage(), $this->context, $this->request->getLanguageRequest()),
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

    public function getPagelet(): HeaderPageletStruct
    {
        return $this->pagelet;
    }

    public function getRequest(): HeaderPageletRequest
    {
        return $this->request;
    }
}