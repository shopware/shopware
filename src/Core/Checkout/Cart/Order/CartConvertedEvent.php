<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class CartConvertedEvent extends NestedEvent
{
    public const NAME = 'cart.converted-to-order.event';

    /**
     * @var CheckoutContext
     */
    private $checkoutContext;

    /**
     * @var OrderConversionContext
     */
    private $conversionContext;

    /**
     * @var Cart
     */
    private $cart;

    /**
     * @var array
     */
    private $originalConvertedCart;

    /**
     * @var array
     */
    private $convertedCart;

    public function __construct(
        Cart $cart,
        array $convertedCart,
        CheckoutContext $checkoutContext,
        OrderConversionContext $conversionContext
    ) {
        $this->checkoutContext = $checkoutContext;
        $this->conversionContext = $conversionContext;
        $this->cart = $cart;
        $this->originalConvertedCart = $convertedCart;
        $this->convertedCart = $convertedCart;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->checkoutContext->getContext();
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function getOriginalConvertedCart(): array
    {
        return $this->originalConvertedCart;
    }

    public function getConvertedCart(): array
    {
        return $this->convertedCart;
    }

    public function setConvertedCart(array $convertedCart): void
    {
        $this->convertedCart = $convertedCart;
    }

    public function getCheckoutContext(): CheckoutContext
    {
        return $this->checkoutContext;
    }

    public function getConversionContext(): OrderConversionContext
    {
        return $this->conversionContext;
    }
}
