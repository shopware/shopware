<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Tax;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(CalculatedTax::class)]
#[Package('checkout')]
class CalculatedTaxTest extends TestCase
{
    public function testConstruct(): void
    {
        $tax = new CalculatedTax(19.0, 19.0, 100.0, 'label');

        static::assertSame(19.0, $tax->getTax());
        static::assertSame(19.0, $tax->getTaxRate());
        static::assertSame(100.0, $tax->getPrice());
        static::assertSame('label', $tax->getLabel());
    }

    public function testIncrement(): void
    {
        $tax1 = new CalculatedTax(19.0, 19.0, 100.0, 'label 1');
        $tax2 = new CalculatedTax(19.0, 19.0, 100.0, 'label 2');
        $tax3 = new CalculatedTax(19.0, 19.0, 100.0);

        $tax1->increment($tax2);
        $tax1->increment($tax3);

        static::assertSame(57.0, $tax1->getTax());
        static::assertSame(19.0, $tax1->getTaxRate());
        static::assertSame(300.0, $tax1->getPrice());
        static::assertSame('label 1 + label 2', $tax1->getLabel());
    }
}
