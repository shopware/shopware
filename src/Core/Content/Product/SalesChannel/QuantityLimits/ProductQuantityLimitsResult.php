<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\QuantityLimits;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('inventory')]
class ProductQuantityLimitsResult extends Struct
{
    public function __construct(
        protected string $productId,
        protected int $minPurchase,
        protected int $purchaseSteps,
        protected int $maxPurchase,
    ) {
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getMinPurchase(): int
    {
        return $this->minPurchase;
    }

    public function getPurchaseSteps(): int
    {
        return $this->purchaseSteps;
    }

    public function getMaxPurchase(): int
    {
        return $this->maxPurchase;
    }
}
