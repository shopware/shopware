<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Price;

use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPrice;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @deprecated tag:v6.6.0 - reason:becomes-final - DTO will become internal in v6.6.0
 */
#[Package('inventory')]
class ReferencePriceDto extends Struct
{
    /**
     * @deprecated tag:v6.6.0 - Native type hint will be added
     *
     * @var float|null
     */
    protected $purchase;

    /**
     * @deprecated tag:v6.6.0 - Native type hint will be added
     *
     * @var float|null
     */
    protected $reference;

    /**
     * @deprecated tag:v6.6.0 - Native type hint will be added
     *
     * @var string|null
     */
    protected $unitId;

    /**
     * @deprecated tag:v6.6.0 - Will be changed to new __constructor signature with native types
     */
    public function __construct(
        ?float $purchase,
        ?float $reference,
        ?string $unitId
    ) {
        $this->purchase = $purchase;
        $this->reference = $reference;
        $this->unitId = $unitId;
    }

    public static function createFromEntity(Entity $product): ReferencePriceDto
    {
        return new self(
            $product->get('purchaseUnit'),
            $product->get('referenceUnit'),
            $product->get('unitId')
        );
    }

    /**
     * @deprecated tag:v6.6.0 - use self::createFromEntity instead
     */
    public static function createFromProduct(ProductEntity $product): ReferencePriceDto
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', self::class . '::createFromEntity')
        );

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
