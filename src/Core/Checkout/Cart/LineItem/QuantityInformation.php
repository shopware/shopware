<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('checkout')]
class QuantityInformation extends Struct
{
    protected int $minPurchase = 1;

    protected ?int $maxPurchase = null;

    protected ?int $purchaseSteps = 1;

    public function getMinPurchase(): int
    {
        return $this->minPurchase;
    }

    public function setMinPurchase(int $minPurchase): QuantityInformation
    {
        if ($minPurchase < 1) {
            throw CartException::unexpectedValueException('minPurchase must be greater or equal 1');
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
            throw CartException::unexpectedValueException('purchaseSteps must be greater or equal 1');
        }

        $this->purchaseSteps = $purchaseSteps;

        return $this;
    }

    public function getApiAlias(): string
    {
        return 'cart_quantity_information';
    }
}
