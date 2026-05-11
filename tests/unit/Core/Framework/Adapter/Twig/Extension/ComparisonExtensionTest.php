<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Adapter\Twig\Extension\ComparisonExtension;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[CoversClass(ComparisonExtension::class)]
class ComparisonExtensionTest extends TestCase
{
    #[DataProvider('comparisonProvider')]
    public function testCompare(bool $expected, string $operator, mixed $value, mixed $comparable = null): void
    {
        $extension = new ComparisonExtension();

        static::assertSame($expected, $extension->compare($operator, $value, $comparable));
    }

    public function testCompareNumericThrowsAnExceptionWhenOperatorIsUnsupported(): void
    {
        $this->expectExceptionObject(AdapterException::unsupportedOperator('$', ComparisonExtension::class));
        $extension = new ComparisonExtension();

        $extension->compare('$', 2, 0);
    }

    public function testCompareMixedThrowsAnExceptionWhenOperatorIsUnsupported(): void
    {
        $this->expectExceptionObject(AdapterException::unsupportedOperator('$', ComparisonExtension::class));
        $extension = new ComparisonExtension();

        $extension->compare('$', '5', 0);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testCompareNumericThrowsAnExceptionWhenOperatorIsUnsupportedDeprecated(): void
    {
        $this->expectExceptionObject(new UnsupportedOperatorException('$', ComparisonExtension::class));
        $extension = new ComparisonExtension();

        $extension->compare('$', 2, 0);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testCompareMixedThrowsAnExceptionWhenOperatorIsUnsupportedDeprecated(): void
    {
        $this->expectExceptionObject(AdapterException::unsupportedOperator('$', ComparisonExtension::class));
        $extension = new ComparisonExtension();

        $extension->compare('$', '5', 0);
    }

    public static function comparisonProvider(): \Generator
    {
        yield 'string equals true' => [true, '=', 'foo', 'foo'];
        yield 'string equals false' => [false, '=', 'foo', 'bar'];
        yield 'float equals true' => [true, '=', 0.123, 0.123];
        yield 'float equals false' => [false, '=', 0.123, 0.456];
        yield 'float and int equals true' => [true, '=', 2, 2.0];
        yield 'float and int equals false' => [false, '=', 2, 2.1];
        yield 'bool equals true' => [true, '=', true, true];
        yield 'bool equals false' => [false, '=', true, false];
        yield 'string equals array true' => [true, '=', 'foo', ['foo', 'bar']];
        yield 'string equals array false' => [false, '=', 'foo', ['bar', 'baz']];
        yield 'array equals array true' => [true, '=', ['foo', 'baz'], ['foo', 'bar']];
        yield 'array equals array false' => [false, '=', ['foo', 'xyz'], ['bar', 'baz']];
        yield 'string not equals true' => [true, '!=', 'foo', 'bar'];
        yield 'string not equals false' => [false, '!=', 'foo', 'foo'];
        yield 'float not equals true' => [true, '!=', 0.123, 0.456];
        yield 'float not equals false' => [false, '!=', 0.123, 0.123];
        yield 'float and int not equals true' => [true, '!=', 2, 2.1];
        yield 'float and int not equals false' => [false, '!=', 2, 2.0];
        yield 'bool not equals true' => [true, '!=', true, false];
        yield 'bool not equals false' => [false, '!=', true, true];
        yield 'string array not equals true' => [true, '!=', 'foo', ['bar', 'baz']];
        yield 'string array not equals false' => [false, '!=', 'foo', ['foo', 'bar']];
        yield 'array not equals array true' => [true, '!=', ['foo', 'xyz'], ['bar', 'baz']];
        yield 'array not equals array false' => [false, '!=', ['foo', 'baz'], ['foo', 'bar']];
        yield 'float greater than true' => [true, '>', 0.123, 0.1];
        yield 'float greater than false' => [false, '>', 0.123, 0.456];
        yield 'float and int greater than true' => [true, '>', 1, 0.1];
        yield 'float and int greater than false' => [false, '>', 0.123, 1];
        yield 'int greater than true' => [true, '>', 2, 1];
        yield 'int greater than false' => [false, '>', 1, 2];
        yield 'datetime greater than true' => [true, '>', new \DateTime('2001-01-01 00:00:00'), new \DateTime('2000-01-01 00:00:00')];
        yield 'datetime greater than false' => [false, '>', new \DateTime('2000-01-01 00:00:00'), new \DateTime('2000-01-01 00:00:00')];
        yield 'float greater than equals true' => [true, '>=', 0.123, 0.123];
        yield 'float greater than equals false' => [false, '>=', 0.123, 0.456];
        yield 'float and int greater than equals true' => [true, '>=', 1, 1.0];
        yield 'float and int greater than equals false' => [false, '>=', 0.123, 2];
        yield 'int greater than equals true' => [true, '>=', 2, 2];
        yield 'int greater than equals false' => [false, '>=', 1, 2];
        yield 'datetime greater than equals true' => [true, '>=', new \DateTime('2001-01-01 00:00:00'), new \DateTime('2001-01-01 00:00:00')];
        yield 'datetime greater than equals false' => [false, '>=', new \DateTime('2000-01-01 00:00:00'), new \DateTime('2001-01-01 00:00:00')];
        yield 'float less than true' => [true, '<', 0.1, 0.123];
        yield 'float less than false' => [false, '<', 0.456, 0.123];
        yield 'float and int less than true' => [true, '<', 0.1, 1];
        yield 'float and int less than false' => [false, '<', 1, 0.123];
        yield 'int less than true' => [true, '<', 1, 2];
        yield 'int less than false' => [false, '<', 2, 1];
        yield 'datetime less than true' => [true, '<', new \DateTime('2000-01-01 00:00:00'), new \DateTime('2001-01-01 00:00:00')];
        yield 'datetime less than false' => [false, '<', new \DateTime('2000-01-01 00:00:00'), new \DateTime('2000-01-01 00:00:00')];
        yield 'float less than equals true' => [true, '<=', 0.123, 0.123];
        yield 'float less than equals false' => [false, '<=', 0.456, 0.123];
        yield 'float and int less than equals true' => [true, '<=', 1, 1.0];
        yield 'float and int less than equals false' => [false, '<=', 2, 0.123];
        yield 'int less than equals true' => [true, '<=', 2, 2];
        yield 'int less than equals false' => [false, '<=', 2, 1];
        yield 'datetime less than equals true' => [true, '<=', new \DateTime('2001-01-01 00:00:00'), new \DateTime('2001-01-01 00:00:00')];
        yield 'datetime less than equals false' => [false, '<=', new \DateTime('2001-01-01 00:00:00'), new \DateTime('2000-01-01 00:00:00')];
        yield 'null empty true' => [true, 'empty', null];
        yield 'zero empty true' => [true, 'empty', 0];
        yield 'false empty true' => [true, 'empty', false];
        yield 'empty string empty true' => [true, 'empty', ''];
        yield 'string empty false' => [false, 'empty', 'foo'];
        yield 'int empty false' => [false, 'empty', 1];
        yield 'bool empty false' => [false, 'empty', true];
    }
}
