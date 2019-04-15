<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem\Struct;

use Shopware\Core\Framework\Struct\Struct;

class QuantityInformation extends Struct
{
    /**
     * @var int|null
     */
    protected $minPurchase;

    /**
     * @var int|null
     */
    protected $maxPurchase;

    /**
     * @var int|null
     */
    protected $purchaseSteps;

    /**
     * @var string|null
     */
    protected $packUnit;

    /**
     * @var float|null
     */
    protected $referenceUnit;

    /**
     * @var float|null
     */
    protected $purchaseUnit;

    public function getMinPurchase(): ?int
    {
        return $this->minPurchase;
    }

    public function setMinPurchase(int $minPurchase): QuantityInformation
    {
        $this->minPurchase = $minPurchase;

        return $this;
    }

    public function getMaxPurchase(): ?int
    {
        return $this->maxPurchase;
    }

    public function setMaxPurchase(int $maxPurchase): QuantityInformation
    {
        $this->maxPurchase = $maxPurchase;

        return $this;
    }

    public function getPurchaseSteps(): ?int
    {
        return $this->purchaseSteps;
    }

    public function setPurchaseSteps(int $purchaseSteps): QuantityInformation
    {
        $this->purchaseSteps = $purchaseSteps;

        return $this;
    }

    public function getPackUnit(): ?string
    {
        return $this->packUnit;
    }

    public function setPackUnit(string $packUnit): QuantityInformation
    {
        $this->packUnit = $packUnit;

        return $this;
    }

    public function getReferenceUnit(): ?float
    {
        return $this->referenceUnit;
    }

    public function setReferenceUnit(float $referenceUnit): QuantityInformation
    {
        $this->referenceUnit = $referenceUnit;

        return $this;
    }

    public function getPurchaseUnit(): ?float
    {
        return $this->purchaseUnit;
    }

    public function setPurchaseUnit(float $purchaseUnit): QuantityInformation
    {
        $this->purchaseUnit = $purchaseUnit;

        return $this;
    }
}
