<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price\Struct;

use Shopware\Core\Framework\Struct\Struct;

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

    private function __construct(float $price, float $discount, float $percentage)
    {
        $this->price = $price;
        $this->discount = $discount;
        $this->percentage = $percentage;
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
        return $this->price;
    }

    public function getDiscount(): float
    {
        return $this->discount;
    }

    public function getPercentage(): float
    {
        return $this->percentage;
    }

    public function getApiAlias(): string
    {
        return 'cart_list_price';
    }
}
