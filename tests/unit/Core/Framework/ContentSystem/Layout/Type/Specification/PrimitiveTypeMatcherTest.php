<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Specification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PrimitiveTypeMatcher;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PrimitiveTypeMatcher::class)]
class PrimitiveTypeMatcherTest extends TestCase
{
    #[DataProvider('primitiveValues')]
    public function testMatchesWithoutCoercion(string $type, mixed $value, bool $expected): void
    {
        static::assertSame($expected, PrimitiveTypeMatcher::matches($value, $type));
    }

    /**
     * @return iterable<string, array{string, mixed, bool}>
     */
    public static function primitiveValues(): iterable
    {
        yield 'a string accepts an empty value' => ['string', '', true];
        yield 'a string accepts numeric text' => ['string', '42', true];
        yield 'a string rejects an integer' => ['string', 42, false];
        yield 'an integer accepts zero' => ['integer', 0, true];
        yield 'an integer rejects an integral float' => ['integer', 42.0, false];
        yield 'an integer rejects numeric text' => ['integer', '42', false];
        yield 'a number accepts an integer' => ['number', 42, true];
        yield 'a number accepts a float' => ['number', 4.2, true];
        yield 'a number rejects numeric text' => ['number', '4.2', false];
        yield 'a number rejects a boolean' => ['number', false, false];
        yield 'a boolean accepts true' => ['boolean', true, true];
        yield 'a boolean accepts false' => ['boolean', false, true];
        yield 'a boolean rejects zero' => ['boolean', 0, false];
        yield 'a boolean rejects text' => ['boolean', 'false', false];
        yield 'a string rejects null' => ['string', null, false];
        yield 'an integer rejects null' => ['integer', null, false];
        yield 'a number rejects null' => ['number', null, false];
        yield 'a boolean rejects null' => ['boolean', null, false];
        yield 'an array is not a string' => ['string', [], false];
        yield 'bare object is outside primitive matching' => ['object', new \stdClass(), false];
        yield 'class names are outside primitive matching' => [\stdClass::class, new \stdClass(), false];
        yield 'unknown type names do not match' => ['unknown', 'value', false];
    }
}
