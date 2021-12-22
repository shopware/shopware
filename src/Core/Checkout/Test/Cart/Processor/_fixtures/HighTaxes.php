<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Processor\_fixtures;

use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;

class HighTaxes extends TaxRuleCollection
{
    public function __construct()
    {
        parent::__construct([new TaxRule(19)]);
    }
}
