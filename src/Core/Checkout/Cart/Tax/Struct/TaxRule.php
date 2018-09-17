<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Tax\Struct;

use Shopware\Core\Framework\Struct\Struct;

class TaxRule extends Struct implements TaxRuleInterface
{
    /**
     * @var float
     */
    protected $taxRate;

    public function __construct(float $taxRate)
    {
        $this->taxRate = $taxRate;
    }

    public function getTaxRate(): float
    {
        return $this->taxRate;
    }
}
