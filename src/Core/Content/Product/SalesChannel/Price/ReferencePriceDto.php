<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Price;

use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPrice;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @final
 */
#[Package('inventory')]
class ReferencePriceDto extends Struct
{
    public function __construct(
        protected ?float $purchase,
        protected ?float $reference,
        protected ?string $unitId
    ) {
    }

    public static function createFromEntity(Entity $product): ReferencePriceDto
    {
        return new self(
            $product->get('purchaseUnit'),
            $product->get('referenceUnit'),
            $product->get('unitId')
        );
    }

    public static function createFromCheapestPrice(CheapestPrice $price): ReferencePriceDto
    {
        return new ReferencePriceDto($price->getPurchase(), $price->getReference(), $price->getUnitId());
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
