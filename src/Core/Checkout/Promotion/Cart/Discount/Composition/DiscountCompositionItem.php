<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\Composition;

use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class DiscountCompositionItem
{
    public function __construct(
        private readonly string $id,
        private readonly int $quantity,
        private readonly float $discountValue
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getDiscountValue(): float
    {
        return $this->discountValue;
    }
}
