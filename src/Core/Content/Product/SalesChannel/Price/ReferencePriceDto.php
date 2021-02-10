<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Price;

use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPrice;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Struct\Struct;

class ReferencePriceDto extends Struct
{
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

    public function __construct(?float $purchase, ?float $reference, ?string $unitId)
    {
        $this->purchase = $purchase;
        $this->reference = $reference;
        $this->unitId = $unitId;
    }

    public static function createFromProduct(ProductEntity $product): ReferencePriceDto
    {
        return new self(
            $product->getPurchaseUnit(),
            $product->getReferenceUnit(),
            $product->getUnitId()
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
