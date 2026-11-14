<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('checkout')]
final class PositionStruct extends Struct
{
    public function __construct(
        protected int $position
    ) {
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getApiAlias(): string
    {
        return 'cart_order_position';
    }
}
