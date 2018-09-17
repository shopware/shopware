<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price\Struct;

use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Struct\Struct;

class QuantityPriceDefinition extends Struct implements PriceDefinitionInterface
{
    /** @var float */
    protected $price;

    /** @var TaxRuleCollection */
    protected $taxRules;

    /** @var int */
    protected $quantity;

    /**
     * @var bool
     */
    protected $isCalculated;

    public function __construct(
        float $price,
        TaxRuleCollection $taxRules,
        int $quantity = 1,
        bool $isCalculated = false
    ) {
        $this->price = $price;
        $this->taxRules = $taxRules;
        $this->quantity = $quantity;
        $this->isCalculated = $isCalculated;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function getTaxRules(): TaxRuleCollection
    {
        return $this->taxRules;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function isCalculated(): bool
    {
        return $this->isCalculated;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }
}
