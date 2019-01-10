<?php

namespace Shopware\Storefront\Pagelet\Navigation;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class NavigationPageletLoadedEvent extends NestedEvent
{
    public const NAME = 'navigation.pagelet.loaded';

    /**
     * @var NavigationPageletStruct
     */
    protected $pagelet;

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var NavigationPageletRequest
     */
    protected $request;

    public function __construct(
        NavigationPageletStruct $pagelet,
        CheckoutContext $context,
        NavigationPageletRequest $request
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

    public function getPagelet(): NavigationPageletStruct
    {
        return $this->pagelet;
    }

    public function getRequest(): NavigationPageletRequest
    {
        return $this->request;
    }
}