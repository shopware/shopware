<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Util\FloatComparator;

#[Package('checkout')]
class ListPrice extends Struct
{
    /**
     * @var float
     */
    protected $price;

    /**
     * @var float
     */
    protected $discount;

    /**
     * @var float
     */
    protected $percentage;

    private function __construct(
        float $price,
        float $discount,
        float $percentage
    ) {
        $this->price = FloatComparator::cast($price);
        $this->discount = FloatComparator::cast($discount);
        $this->percentage = FloatComparator::cast($percentage);
    }

    public static function createFromUnitPrice(float $unitPrice, float $listPrice): ListPrice
    {
        return new self(
            $listPrice,
            ($listPrice - $unitPrice) * -1,
            round(100 - $unitPrice / $listPrice * 100, 2)
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
        return 'cart_list_price';
    }
}
