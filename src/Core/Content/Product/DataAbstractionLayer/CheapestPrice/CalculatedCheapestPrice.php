<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class CalculatedCheapestPrice extends CalculatedPrice
{
    protected bool $hasRange = false;

    protected bool $hasListPriceRange = false;

    protected bool $hasDisplayableListPrice = false;

    protected ?string $variantId = null;

    public function hasRange(): bool
    {
        return $this->hasRange;
    }

    public function setHasRange(bool $hasRange): void
    {
        $this->hasRange = $hasRange;
    }

    /**
     * True when the displayable list prices of the variants differ from each other,
     * which makes the concrete list price of the cheapest variant unrepresentative.
     */
    public function hasListPriceRange(): bool
    {
        return $this->hasListPriceRange;
    }

    public function setHasListPriceRange(bool $hasListPriceRange): void
    {
        $this->hasListPriceRange = $hasListPriceRange;
    }

    /**
     * True when at least one variant has a list price with a discount greater than zero percent.
     */
    public function hasDisplayableListPrice(): bool
    {
        return $this->hasDisplayableListPrice;
    }

    public function setHasDisplayableListPrice(bool $hasDisplayableListPrice): void
    {
        $this->hasDisplayableListPrice = $hasDisplayableListPrice;
    }

    public function getApiAlias(): string
    {
        return 'calculated_cheapest_price';
    }

    public function setVariantId(string $variantId): void
    {
        $this->variantId = $variantId;
    }

    public function getVariantId(): ?string
    {
        return $this->variantId;
    }
}
