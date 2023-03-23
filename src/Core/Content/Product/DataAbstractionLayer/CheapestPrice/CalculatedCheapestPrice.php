<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class CalculatedCheapestPrice extends CalculatedPrice
{
    /**
     * @var bool
     */
    protected $hasRange = false;

    protected ?string $variantId = null;

    public function hasRange(): bool
    {
        return $this->hasRange;
    }

    public function setHasRange(bool $hasRange): void
    {
        $this->hasRange = $hasRange;
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
