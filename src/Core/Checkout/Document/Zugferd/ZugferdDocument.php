<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Zugferd;

use horstoeko\zugferd\ZugferdDocumentBuilder;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class ZugferdDocument
{
    protected float $chargeAmount = 0.0;

    protected float $lineTotalAmount = 0.0;

    protected float $allowanceAmount = 0.0;

    public function __construct(
        public readonly OrderEntity $order,
        public readonly DocumentGenerateOperation $operation,
        public readonly ZugferdDocumentBuilder $zugferdBuilder,
        public readonly bool $isGross
    ) {
    }

    public function getChargeAmount(): float
    {
        return $this->chargeAmount;
    }

    public function getLineTotalAmount(): float
    {
        return $this->lineTotalAmount;
    }

    public function getAllowanceAmount(): float
    {
        return $this->allowanceAmount;
    }

    public function addChargeAmount(float $chargeAmount): void
    {
        $this->chargeAmount += $chargeAmount;
    }

    public function addLineTotalAmount(float $lineTotalAmount): void
    {
        $this->lineTotalAmount += $lineTotalAmount;
    }

    public function addAllowanceAmount(float $allowanceAmount): void
    {
        $this->allowanceAmount += $allowanceAmount;
    }
}
