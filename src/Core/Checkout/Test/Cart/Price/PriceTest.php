<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Price;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;

class PriceTest extends TestCase
{
    /**
     * @dataProvider addCases
     */
    public function testAdd(CalculatedPrice $a, CalculatedPrice $b, CalculatedPrice $expected): void
    {
        $a->add($b);
        static::assertEquals($expected->getQuantity(), $a->getQuantity());
        static::assertEquals($expected->getUnitPrice(), $a->getUnitPrice());
        static::assertEquals($expected->getUnitPrice(), $a->getUnitPrice());
        static::assertEquals($expected->getTotalPrice(), $a->getTotalPrice());
        static::assertEquals($expected->getTaxRules(), $a->getTaxRules());
        static::assertEquals($expected->getCalculatedTaxes(), $a->getCalculatedTaxes());
        static::assertEquals($expected, $a);
    }

    /**
     * @dataProvider subCases
     */
    public function testSub(CalculatedPrice $a, CalculatedPrice $b, CalculatedPrice $expected): void
    {
        $a->sub($b);
        static::assertEquals($expected->getQuantity(), $a->getQuantity());
        static::assertEquals($expected->getUnitPrice(), $a->getUnitPrice());
        static::assertEquals($expected->getUnitPrice(), $a->getUnitPrice());
        static::assertEquals($expected->getTotalPrice(), $a->getTotalPrice());
        static::assertEquals($expected->getTaxRules(), $a->getTaxRules());
        static::assertEquals($expected->getCalculatedTaxes(), $a->getCalculatedTaxes());
        static::assertEquals($expected, $a);
    }

    public function addCases(): array
    {
        return [
            [
                new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()),
                new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()),
                new CalculatedPrice(2, 2, new CalculatedTaxCollection(), new TaxRuleCollection()),
            ],
            [
                new CalculatedPrice(1, 1, new CalculatedTaxCollection([new CalculatedTax(0.55, 19, 1)]), new TaxRuleCollection()),
                new CalculatedPrice(1, 1, new CalculatedTaxCollection([new CalculatedTax(0.55, 19, 1)]), new TaxRuleCollection()),
                new CalculatedPrice(2, 2, new CalculatedTaxCollection([new CalculatedTax(1.10, 19, 2)]), new TaxRuleCollection()),
            ],
            [
                new CalculatedPrice(1, 1, new CalculatedTaxCollection([new CalculatedTax(0.55, 19, 1)]), new TaxRuleCollection()),
                new CalculatedPrice(-0.5, -0.5, new CalculatedTaxCollection([new CalculatedTax(-0.5, 19, -0.5)]), new TaxRuleCollection()),
                new CalculatedPrice(0.5, 0.5, new CalculatedTaxCollection([new CalculatedTax(0.05, 19, 0.5)]), new TaxRuleCollection()),
            ],
        ];
    }

    public function subCases(): array
    {
        return [
            [
                new CalculatedPrice(2, 2, new CalculatedTaxCollection(), new TaxRuleCollection()),
                new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()),
                new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()),
            ],
        ];
    }
}
