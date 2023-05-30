<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Adapter\Twig\Extension;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Extension\ComparisonExtension;

/**
 * @internal
 */
class ComparisonExtensionTest extends TestCase
{
    /**
     * @dataProvider comparisonProvider
     */
    public function testCompare(bool $shouldBeTrue, string $operator, mixed $value, mixed $comparable = null): void
    {
        $extension = new ComparisonExtension();
        $comparison = $extension->compare($operator, $value, $comparable);

        if ($shouldBeTrue) {
            static::assertTrue($comparison);
        } else {
            static::assertFalse($comparison);
        }
    }

    public static function comparisonProvider(): \Generator
    {
        // equals
        yield 'Test string / equals / true' => [true, '=', 'foo', 'foo'];
        yield 'Test string / equals / false' => [false, '=', 'foo', 'bar'];
        yield 'Test float / equals / true' => [true, '=', 0.123, 0.123];
        yield 'Test float / equals / false' => [false, '=', 0.123, 0.456];
        yield 'Test float+int / equals / true' => [true, '=', 2, 2.0];
        yield 'Test float+int / equals / false' => [false, '=', 2, 2.1];
        yield 'Test bool / equals / true' => [true, '=', true, true];
        yield 'Test bool / equals / false' => [false, '=', true, false];
        yield 'Test string+array / equals / true' => [true, '=', 'foo', ['foo', 'bar']];
        yield 'Test string+array / equals / false' => [false, '=', 'foo', ['bar', 'baz']];
        yield 'Test array+array / equals / true' => [true, '=', ['foo', 'baz'], ['foo', 'bar']];
        yield 'Test array+array / equals / false' => [false, '=', ['foo', 'xyz'], ['bar', 'baz']];
        // not equals
        yield 'Test string / not equals / true' => [true, '!=', 'foo', 'bar'];
        yield 'Test string / not equals / false' => [false, '!=', 'foo', 'foo'];
        yield 'Test float / not equals / true' => [true, '!=', 0.123, 0.456];
        yield 'Test float / not equals / false' => [false, '!=', 0.123, 0.123];
        yield 'Test float+int / not equals / true' => [true, '!=', 2, 2.1];
        yield 'Test float+int / not equals / false' => [false, '!=', 2, 2.0];
        yield 'Test bool / not equals / true' => [true, '!=', true, false];
        yield 'Test bool / not equals / false' => [false, '!=', true, true];
        yield 'Test string+array / not equals / true' => [true, '!=', 'foo', ['bar', 'baz']];
        yield 'Test string+array / not equals / false' => [false, '!=', 'foo', ['foo', 'bar']];
        yield 'Test array+array / not equals / true' => [true, '!=', ['foo', 'xyz'], ['bar', 'baz']];
        yield 'Test array+array / not equals / false' => [false, '!=', ['foo', 'baz'], ['foo', 'bar']];
        // greater than
        yield 'Test float / gt / true' => [true, '>', 0.123, 0.1];
        yield 'Test float / gt / false' => [false, '>', 0.123, 0.456];
        yield 'Test float+int / gt / true' => [true, '>', 1, 0.1];
        yield 'Test float+int / gt / false' => [false, '>', 0.123, 1];
        yield 'Test int / gt / true' => [true, '>', 2, 1];
        yield 'Test int / gt / false' => [false, '>', 1, 2];
        yield 'Test datetime / gt / true' => [true, '>', new \DateTime('2001-01-01 00:00:00'), new \DateTime('2000-01-01 00:00:00')];
        yield 'Test datetime / gt / false' => [false, '>', new \DateTime('2000-01-01 00:00:00'), new \DateTime('2000-01-01 00:00:00')];
        // greater than equal
        yield 'Test float / gte / true' => [true, '>=', 0.123, 0.123];
        yield 'Test float / gte / false' => [false, '>=', 0.123, 0.456];
        yield 'Test float+int / gte / true' => [true, '>=', 1, 1.0];
        yield 'Test float+int / gte / false' => [false, '>=', 0.123, 2];
        yield 'Test int / gte / true' => [true, '>=', 2, 2];
        yield 'Test int / gte / false' => [false, '>=', 1, 2];
        yield 'Test datetime / gte / true' => [true, '>=', new \DateTime('2001-01-01 00:00:00'), new \DateTime('2001-01-01 00:00:00')];
        yield 'Test datetime / gte / false' => [false, '>=', new \DateTime('2000-01-01 00:00:00'), new \DateTime('2001-01-01 00:00:00')];
        // less than
        yield 'Test float / lt / true' => [true, '<', 0.1, 0.123];
        yield 'Test float / lt / false' => [false, '<', 0.456, 0.123];
        yield 'Test float+int / lt / true' => [true, '<', 0.1, 1];
        yield 'Test float+int / lt / false' => [false, '<', 1, 0.123];
        yield 'Test int / lt / true' => [true, '<', 1, 2];
        yield 'Test int / lt / false' => [false, '<', 2, 1];
        yield 'Test datetime / lt / true' => [true, '<', new \DateTime('2000-01-01 00:00:00'), new \DateTime('2001-01-01 00:00:00')];
        yield 'Test datetime / lt / false' => [false, '<', new \DateTime('2000-01-01 00:00:00'), new \DateTime('2000-01-01 00:00:00')];
        // less than equal
        yield 'Test float / lte / true' => [true, '<=', 0.123, 0.123];
        yield 'Test float / lte / false' => [false, '<=', 0.456, 0.123];
        yield 'Test float+int / lte / true' => [true, '<=', 1, 1.0];
        yield 'Test float+int / lte / false' => [false, '<=', 2, 0.123];
        yield 'Test int / lte / true' => [true, '<=', 2, 2];
        yield 'Test int / lte / false' => [false, '<=', 2, 1];
        yield 'Test datetime / lte / true' => [true, '<=', new \DateTime('2001-01-01 00:00:00'), new \DateTime('2001-01-01 00:00:00')];
        yield 'Test datetime / lte / false' => [false, '<=', new \DateTime('2001-01-01 00:00:00'), new \DateTime('2000-01-01 00:00:00')];
        // empty
        yield 'Test null / empty / true' => [true, 'empty', null];
        yield 'Test 0 / empty / true' => [true, 'empty', 0];
        yield 'Test bool / empty / true' => [true, 'empty', false];
        yield 'Test empty string / empty / true' => [true, 'empty', ''];
        yield 'Test string / empty / false' => [false, 'empty', 'foo'];
        yield 'Test int / empty / false' => [false, 'empty', 1];
        yield 'Test bool / empty / false' => [false, 'empty', true];
    }
}
