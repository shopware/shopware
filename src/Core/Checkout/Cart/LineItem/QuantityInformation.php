<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('checkout')]
class QuantityInformation extends Struct
{
    /**
     * @var int
     */
    protected $minPurchase = 1;

    /**
     * @var int|null
     */
    protected $maxPurchase;

    /**
     * @var int|null
     */
    protected $purchaseSteps = 1;

    public function getMinPurchase(): int
    {
        return $this->minPurchase;
    }

    public function setMinPurchase(int $minPurchase): QuantityInformation
    {
        if ($minPurchase < 1) {
            throw new \UnexpectedValueException('minPurchase must be greater or equal 1');
        }

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
        if ($purchaseSteps < 1) {
            throw new \UnexpectedValueException('purchaseSteps must be greater or equal 1');
        }

        $this->purchaseSteps = $purchaseSteps;

        return $this;
    }

    public function getApiAlias(): string
    {
        return 'cart_quantity_information';
    }
}
