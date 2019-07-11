<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price\Struct;

use Shopware\Core\Framework\Struct\Struct;

class ReferencePriceDefinition extends Struct
{
    /**
     * @var float
     */
    protected $purchaseUnit;

    /**
     * @var float
     */
    protected $referenceUnit;

    /**
     * @var string
     */
    protected $unitName;

    public function __construct(float $purchaseUnit, float $referenceUnit, string $unitName)
    {
        $this->purchaseUnit = $purchaseUnit;
        $this->referenceUnit = $referenceUnit;
        $this->unitName = $unitName;
    }

    public function getPurchaseUnit(): float
    {
        return $this->purchaseUnit;
    }

    public function getReferenceUnit(): float
    {
        return $this->referenceUnit;
    }

    public function getUnitName(): string
    {
        return $this->unitName;
    }
}
