<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Header;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Storefront\Pagelet\CartInfo\CartInfoPageletRequestEvent;
use Shopware\Storefront\Pagelet\Currency\CurrencyPageletRequestEvent;
use Shopware\Storefront\Pagelet\Language\LanguagePageletRequestEvent;
use Shopware\Storefront\Pagelet\Navigation\NavigationPageletRequestEvent;
use Shopware\Storefront\Pagelet\Shopmenu\ShopmenuPageletRequestEvent;
use Symfony\Component\HttpFoundation\Request;

class HeaderPageletRequestEvent extends NestedEvent
{
    public const NAME = 'header.pagelet.request';

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var Request
     */
    protected $httpRequest;

    /**
     * @var HeaderPageletRequest
     */
    protected $pageletRequest;

    public function __construct(Request $httpRequest, CheckoutContext $context, HeaderPageletRequest $pageletRequest)
    {
        $this->context = $context;
        $this->httpRequest = $httpRequest;
        $this->pageletRequest = $pageletRequest;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection([
            new NavigationPageletRequestEvent($this->httpRequest, $this->context, $this->pageletRequest->getNavigationRequest()),
            new CartInfoPageletRequestEvent($this->httpRequest, $this->context, $this->pageletRequest->getCartInfoRequest()),
            new ShopmenuPageletRequestEvent($this->httpRequest, $this->context, $this->pageletRequest->getShopmenuRequest()),
            new CurrencyPageletRequestEvent($this->httpRequest, $this->context, $this->pageletRequest->getCurrencyRequest()),
            new LanguagePageletRequestEvent($this->httpRequest, $this->context, $this->pageletRequest->getLanguageRequest()),
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

    public function getHttpRequest(): Request
    {
        return $this->httpRequest;
    }

    public function getPageletRequest(): HeaderPageletRequest
    {
        return $this->pageletRequest;
    }

}
