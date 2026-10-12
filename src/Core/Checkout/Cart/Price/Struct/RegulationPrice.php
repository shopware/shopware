<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price\Struct;

use Shopware\Core\Framework\Deprecation\BCChange\VisibilityChange;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Util\FloatComparator;

#[Package('checkout')]
class RegulationPrice extends Struct
{
    #[VisibilityChange(version: 'v6.8.0', newVisibility: 'private', description: 'Matches ListPrice, use createFromUnitPrice() instead.')]
    public function __construct(
        protected float $price,
        protected float $discount = 0.0,
        protected float $percentage = 0.0
    ) {
        $this->price = FloatComparator::cast($price);
        $this->discount = FloatComparator::cast($discount);
        $this->percentage = FloatComparator::cast($percentage);
    }

    public static function createFromUnitPrice(float $unitPrice, float $regulationPrice): RegulationPrice
    {
        return new self(
            $regulationPrice,
            PriceReduction::discount($unitPrice, $regulationPrice),
            PriceReduction::percentage($unitPrice, $regulationPrice)
        );
    }

    public function getPrice(): float
    {
        return FloatComparator::cast($this->price);
    }

    public function getDiscount(): float
    {
        return FloatComparator::cast($this->discount);
    }

    public function getPercentage(): float
    {
        return FloatComparator::cast($this->percentage);
    }

    public function getApiAlias(): string
    {
        return 'cart_regulation_price';
    }
}
