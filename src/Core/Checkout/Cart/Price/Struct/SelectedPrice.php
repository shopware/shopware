<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price\Struct;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
final class SelectedPrice
{
    public function __construct(
        private readonly float $value,
        private readonly bool $isCalculated
    ) {
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function isCalculated(): bool
    {
        return $this->isCalculated;
    }
}
