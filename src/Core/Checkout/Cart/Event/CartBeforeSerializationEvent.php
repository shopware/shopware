<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Event;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('checkout')]
class CartBeforeSerializationEvent extends Event
{
    /**
     * @param array<string> $customFieldAllowList
     */
    public function __construct(
        protected Cart $cart,
        private array $customFieldAllowList
    ) {
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    /**
     * @return array<string>
     */
    public function getCustomFieldAllowList(): array
    {
        return $this->customFieldAllowList;
    }

    /**
     * @param array<string> $customFieldAllowList
     */
    public function setCustomFieldAllowList(array $customFieldAllowList): void
    {
        $this->customFieldAllowList = $customFieldAllowList;
    }

    public function addCustomFieldToAllowList(string $customField): void
    {
        $this->customFieldAllowList[] = $customField;
    }
}
