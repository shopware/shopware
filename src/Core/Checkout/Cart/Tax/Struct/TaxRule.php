<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Tax\Struct;

use Shopware\Core\Framework\Struct\Struct;

class TaxRule extends Struct
{
    /**
     * @var float
     */
    protected $taxRate;

    /**
     * @var float
     */
    protected $percentage;

    public function __construct(float $taxRate, float $percentage = 100.0)
    {
        $this->taxRate = $taxRate;
        $this->percentage = $percentage;
    }

    public function getTaxRate(): float
    {
        return $this->taxRate;
    }

    public function getPercentage(): float
    {
        return $this->percentage;
    }
}
