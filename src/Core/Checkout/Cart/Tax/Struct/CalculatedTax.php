<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Tax\Struct;

use Shopware\Core\Framework\Struct\Struct;

class CalculatedTax extends Struct
{
    /**
     * @var float
     */
    protected $tax = 0;

    /**
     * @var float
     */
    protected $taxRate;

    /**
     * @var float
     */
    protected $price = 0;

    public function __construct(float $tax, float $taxRate, float $price)
    {
        $this->tax = $tax;
        $this->taxRate = $taxRate;
        $this->price = $price;
    }

    public function getTax(): float
    {
        return $this->tax;
    }

    public function setTax(float $tax): void
    {
        $this->tax = $tax;
    }

    public function getTaxRate(): float
    {
        return $this->taxRate;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function increment(self $calculatedTax): void
    {
        $this->tax += $calculatedTax->getTax();
        $this->price += $calculatedTax->getPrice();
    }

    public function setPrice(float $price): void
    {
        $this->price = $price;
    }

    public function getApiAlias(): string
    {
        return 'cart_tax_calculated';
    }
}
