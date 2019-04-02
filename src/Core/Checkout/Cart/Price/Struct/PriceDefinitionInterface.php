<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price\Struct;

interface PriceDefinitionInterface
{
    /**
     * Returns the decimal precision for the price. Necessary for \Shopware\Core\Checkout\Cart\Price\PriceRounding::round
     */
    public function getPrecision(): int;

    public function getType(): string;

    public function getPriority(): int;

    public static function getConstraints(): array;
}
