<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class CartConvertedEvent extends NestedEvent implements ShopwareSalesChannelEvent
{
    /**
     * @var list<mixed>
     */
    private array $convertedCart;

    /**
     * @param list<mixed> $originalConvertedCart
     */
    public function __construct(
        private readonly Cart $cart,
        private readonly array $originalConvertedCart,
        private readonly SalesChannelContext $salesChannelContext,
        private readonly OrderConversionContext $conversionContext
    ) {
        $this->convertedCart = $originalConvertedCart;
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
     * @return list<mixed>
     */
    public function getOriginalConvertedCart(): array
    {
        return $this->originalConvertedCart;
    }

    /**
     * @return list<mixed>
     */
    public function getConvertedCart(): array
    {
        return $this->convertedCart;
    }

    /**
     * @param list<mixed> $convertedCart
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
