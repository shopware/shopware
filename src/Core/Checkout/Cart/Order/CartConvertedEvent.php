<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @package checkout
 */
class CartConvertedEvent extends NestedEvent implements ShopwareSalesChannelEvent
{
    private SalesChannelContext $salesChannelContext;

    private OrderConversionContext $conversionContext;

    private Cart $cart;

    /**
     * @var array<mixed>
     */
    private array $originalConvertedCart;

    /**
     * @var array<mixed>
     */
    private array $convertedCart;

    /**
     * @param array<mixed> $convertedCart
     */
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

    /**
     * @return mixed[]
     */
    public function getOriginalConvertedCart(): array
    {
        return $this->originalConvertedCart;
    }

    /**
     * @return mixed[]
     */
    public function getConvertedCart(): array
    {
        return $this->convertedCart;
    }

    /**
     * @param mixed[] $convertedCart
     */
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
