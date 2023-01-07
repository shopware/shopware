<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Event;

use Shopware\Core\Checkout\Cart\Cart;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @package checkout
 */
class CartBeforeSerializationEvent extends Event
{
    protected Cart $cart;

    /**
     * @var array<mixed>
     */
    private array $customFieldAllowList;

    /**
     * @param array<mixed> $customFieldAllowList
     */
    public function __construct(Cart $cart, array $customFieldAllowList)
    {
        $this->cart = $cart;
        $this->customFieldAllowList = $customFieldAllowList;
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    /**
     * @return array<mixed>
     */
    public function getCustomFieldAllowList(): array
    {
        return $this->customFieldAllowList;
    }

    /**
     * @param array<mixed> $customFieldAllowList
     */
    public function setCustomFieldAllowList(array $customFieldAllowList): void
    {
        $this->customFieldAllowList = $customFieldAllowList;
    }
}
