<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice;

use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('core')]
class CheapestPrice extends Struct
{
    /**
     * @var bool
     */
    protected $hasRange;

    /**
     * @var string
     */
    protected $variantId;

    /**
     * @var string
     */
    protected $parentId;

    /**
     * @var string|null
     */
    protected $ruleId;

    /**
     * @var float|null
     */
    protected $purchase;

    /**
     * @var float|null
     */
    protected $reference;

    /**
     * @var string|null
     */
    protected $unitId;

    /**
     * @var PriceCollection
     */
    protected $price;

    public function getCurrencyPrice(string $currencyId): ?Price
    {
        return $this->price->getCurrencyPrice($currencyId);
    }

    public function getVariantId(): string
    {
        return $this->variantId;
    }

    public function setVariantId(string $variantId): void
    {
        $this->variantId = $variantId;
    }

    public function getRuleId(): ?string
    {
        return $this->ruleId;
    }

    public function setRuleId(?string $ruleId): void
    {
        $this->ruleId = $ruleId;
    }

    public function getPrice(): PriceCollection
    {
        return $this->price;
    }

    public function setPrice(PriceCollection $price): void
    {
        $this->price = $price;
    }

    public function hasRange(): bool
    {
        return $this->hasRange;
    }

    public function setHasRange(bool $hasRange): void
    {
        $this->hasRange = $hasRange;
    }

    public function getParentId(): string
    {
        return $this->parentId;
    }

    public function setParentId(string $parentId): void
    {
        $this->parentId = $parentId;
    }

    public function getPurchase(): ?float
    {
        return $this->purchase;
    }

    public function setPurchase(?float $purchase): void
    {
        $this->purchase = $purchase;
    }

    public function getReference(): ?float
    {
        return $this->reference;
    }

    public function setReference(?float $reference): void
    {
        $this->reference = $reference;
    }

    public function getUnitId(): ?string
    {
        return $this->unitId;
    }

    public function setUnitId(?string $unitId): void
    {
        $this->unitId = $unitId;
    }
}
