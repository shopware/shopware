<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\PurchaseLimit;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductPurchaseLimit extends Struct
{
    public function __construct(
        protected string $productId,
        protected int $minPurchase,
        protected int $purchaseSteps,
        protected int $maxPurchase,
        protected ?int $stock = null,
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

    public function getStock(): ?int
    {
        return $this->stock;
    }

    public function getApiAlias(): string
    {
        return 'product_purchase_limit';
    }
}
