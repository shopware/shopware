<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CartConvertedEvent extends NestedEvent implements ShopwareSalesChannelEvent
{
    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

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
        SalesChannelContext $salesChannelContext,
        OrderConversionContext $conversionContext
    ) {
        $this->salesChannelContext = $salesChannelContext;
        $this->conversionContext = $conversionContext;
        $this->cart = $cart;
        $this->originalConvertedCart = $convertedCart;
        $this->convertedCart = $convertedCart;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
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

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getConversionContext(): OrderConversionContext
    {
        return $this->conversionContext;
    }
}
