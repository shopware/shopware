<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Processor\_fixtures;

use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
class LowTaxes extends TaxRuleCollection
{
    public function __construct()
    {
        parent::__construct([new TaxRule(7)]);
    }
}
