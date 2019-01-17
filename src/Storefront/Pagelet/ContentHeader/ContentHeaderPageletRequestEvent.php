<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\ContentHeader;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Storefront\Pagelet\CartInfo\CartInfoPageletRequestEvent;
use Shopware\Storefront\Pagelet\ContentCurrency\ContentCurrencyPageletRequestEvent;
use Shopware\Storefront\Pagelet\ContentLanguage\ContentLanguagePageletRequestEvent;
use Shopware\Storefront\Pagelet\Navigation\NavigationPageletRequestEvent;
use Shopware\Storefront\Pagelet\Shopmenu\ShopmenuPageletRequestEvent;
use Symfony\Component\HttpFoundation\Request;

class ContentHeaderPageletRequestEvent extends NestedEvent
{
    public const NAME = 'content-header.pagelet.request.event';

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var Request
     */
    protected $httpRequest;

    /**
     * @var ContentHeaderPageletRequest
     */
    protected $pageletRequest;

    public function __construct(Request $httpRequest, CheckoutContext $context, ContentHeaderPageletRequest $pageletRequest)
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
            new ContentCurrencyPageletRequestEvent($this->httpRequest, $this->context, $this->pageletRequest->getCurrencyRequest()),
            new ContentLanguagePageletRequestEvent($this->httpRequest, $this->context, $this->pageletRequest->getLanguageRequest()),
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

    public function getPageletRequest(): ContentHeaderPageletRequest
    {
        return $this->pageletRequest;
    }
}
