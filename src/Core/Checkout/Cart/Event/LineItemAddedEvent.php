<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Event;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

class LineItemAddedEvent extends Event
{
    /**
     * @var LineItem
     */
    protected $lineItem;

    /**
     * @var Cart
     */
    protected $cart;

    /**
     * @var SalesChannelContext
     */
    protected $context;

    /**
     * @var bool
     */
    protected $merged;

    public function __construct(LineItem $lineItem, Cart $cart, SalesChannelContext $context, bool $merged = false)
    {
        $this->lineItem = $lineItem;
        $this->cart = $cart;
        $this->context = $context;
        $this->merged = $merged;
    }

    public function getLineItem(): LineItem
    {
        return $this->lineItem;
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function getContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function isMerged(): bool
    {
        return $this->merged;
    }
}
