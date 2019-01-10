<?php

namespace Shopware\Storefront\Pagelet\Currency;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class CurrencyPageletLoadedEvent extends NestedEvent
{
    public const NAME = 'currency.pagelet.loaded';

    /**
     * @var CurrencyPageletStruct
     */
    protected $pagelet;

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var CurrencyPageletRequest
     */
    protected $request;

    public function __construct(
        CurrencyPageletStruct $pagelet,
        CheckoutContext $context,
        CurrencyPageletRequest $request
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

    public function getPagelet(): CurrencyPageletStruct
    {
        return $this->pagelet;
    }

    public function getRequest(): CurrencyPageletRequest
    {
        return $this->request;
    }
}