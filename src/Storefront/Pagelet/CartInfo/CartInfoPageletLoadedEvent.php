<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\CartInfo;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class CartInfoPageletLoadedEvent extends NestedEvent
{
    public const NAME = 'cart-info.pagelet.loaded.event';

    /**
     * @var CartInfoPageletStruct
     */
    protected $pagelet;

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var CartInfoPageletRequest
     */
    protected $request;

    public function __construct(
        CartInfoPageletStruct $pagelet,
        CheckoutContext $context,
        CartInfoPageletRequest $request
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

    public function getPagelet(): CartInfoPageletStruct
    {
        return $this->pagelet;
    }

    public function getRequest(): CartInfoPageletRequest
    {
        return $this->request;
    }
}
