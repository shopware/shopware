<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\ContentHeader;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Storefront\Pagelet\CartInfo\CartInfoPageletLoadedEvent;
use Shopware\Storefront\Pagelet\ContentCurrency\ContentCurrencyPageletLoadedEvent;
use Shopware\Storefront\Pagelet\ContentLanguage\ContentLanguagePageletLoadedEvent;
use Shopware\Storefront\Pagelet\Navigation\NavigationPageletLoadedEvent;
use Shopware\Storefront\Pagelet\Shopmenu\ShopmenuPageletLoadedEvent;

class ContentHeaderPageletLoadedEvent extends NestedEvent
{
    public const NAME = 'content-header.pagelet.loaded.event';

    /**
     * @var ContentHeaderPageletStruct
     */
    protected $pagelet;

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var ContentHeaderPageletRequest
     */
    protected $request;

    public function __construct(ContentHeaderPageletStruct $page, CheckoutContext $context, ContentHeaderPageletRequest $request)
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
            new ContentCurrencyPageletLoadedEvent($this->pagelet->getCurrency(), $this->context, $this->request->getCurrencyRequest()),
            new ContentLanguagePageletLoadedEvent($this->pagelet->getLanguage(), $this->context, $this->request->getLanguageRequest()),
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

    public function getPagelet(): ContentHeaderPageletStruct
    {
        return $this->pagelet;
    }

    public function getRequest(): ContentHeaderPageletRequest
    {
        return $this->request;
    }
}
