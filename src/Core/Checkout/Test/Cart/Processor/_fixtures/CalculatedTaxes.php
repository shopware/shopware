<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Processor\_fixtures;

use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
class CalculatedTaxes extends CalculatedTaxCollection
{
    public function __construct(array $taxes = [])
    {
        parent::__construct();
        foreach ($taxes as $rate => $value) {
            $this->add(new CalculatedTax($value, $rate, 0));
        }
    }
}
