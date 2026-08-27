<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoredValue::class)]
class StoredValueTest extends TestCase
{
    /**
     * @return iterable<string, array{StoredValue, mixed}>
     */
    public static function variantConstructorProvider(): iterable
    {
        yield 'ofNull' => [StoredValue::ofNull(), null];
        yield 'ofString' => [StoredValue::ofString('headline'), 'headline'];
        yield 'ofInt' => [StoredValue::ofInt(42), 42];
        yield 'ofFloat' => [StoredValue::ofFloat(1.5), 1.5];
        yield 'ofBool' => [StoredValue::ofBool(true), true];
        yield 'ofList' => [StoredValue::ofList([StoredValue::ofString('a'), StoredValue::ofInt(1)]), ['a', 1]];
        yield 'ofMap' => [StoredValue::ofMap(['x' => StoredValue::ofString('a')]), ['x' => 'a']];
    }

    /**
     * @return iterable<string, array{StoredValue, callable(StoredValue): (string|int|float|bool), string|int|float|bool}>
     */
    public static function matchingScalarAccessorProvider(): iterable
    {
        yield 'asString on a string' => [
            StoredValue::ofString('headline'),
            static fn (StoredValue $value): string => $value->asString(),
            'headline',
        ];
        yield 'asInt on an int' => [
            StoredValue::ofInt(42),
            static fn (StoredValue $value): int => $value->asInt(),
            42,
        ];
        yield 'asFloat on a float' => [
            StoredValue::ofFloat(1.5),
            static fn (StoredValue $value): float => $value->asFloat(),
            1.5,
        ];
        yield 'asBool on a bool' => [
            StoredValue::ofBool(false),
            static fn (StoredValue $value): bool => $value->asBool(),
            false,
        ];
    }

    /**
     * @return iterable<string, array{StoredValue, callable(StoredValue): mixed, ContentSystemException}>
     */
    public static function mismatchedAccessorProvider(): iterable
    {
        yield 'asString on an int' => [
            StoredValue::ofInt(42),
            static fn (StoredValue $value): string => $value->asString(),
            ContentSystemException::invalidFieldType('string', 'int'),
        ];
        yield 'asInt on a string' => [
            StoredValue::ofString('42'),
            static fn (StoredValue $value): int => $value->asInt(),
            ContentSystemException::invalidFieldType('int', 'string'),
        ];
        yield 'asFloat on an int' => [
            StoredValue::ofInt(1),
            static fn (StoredValue $value): float => $value->asFloat(),
            ContentSystemException::invalidFieldType('float', 'int'),
        ];
        yield 'asBool on a null' => [
            StoredValue::ofNull(),
            static fn (StoredValue $value): bool => $value->asBool(),
            ContentSystemException::invalidFieldType('bool', 'null'),
        ];
        yield 'asList on a map' => [
            StoredValue::ofMap(['x' => StoredValue::ofInt(1)]),
            static fn (StoredValue $value): array => $value->asList(),
            ContentSystemException::invalidFieldType('list', 'map'),
        ];
        yield 'asMap on a list' => [
            StoredValue::ofList([StoredValue::ofInt(1)]),
            static fn (StoredValue $value): array => $value->asMap(),
            ContentSystemException::invalidFieldType('map', 'list'),
        ];
    }

    /**
     * @return iterable<string, array{StoredValue, bool}>
     */
    public static function nullVariantProvider(): iterable
    {
        yield 'null variant' => [StoredValue::ofNull(), true];
        yield 'empty string' => [StoredValue::ofString(''), false];
        yield 'zero int' => [StoredValue::ofInt(0), false];
        yield 'false bool' => [StoredValue::ofBool(false), false];
        yield 'empty list' => [StoredValue::ofList([]), false];
        yield 'empty map' => [StoredValue::ofMap([]), false];
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function decodableValueProvider(): iterable
    {
        yield 'null' => [null];
        yield 'string' => ['headline'];
        yield 'int' => [42];
        yield 'float' => [1.5];
        yield 'bool' => [true];
        yield 'empty array' => [[]];
        yield 'flat list' => [['a', 'b']];
        yield 'flat map' => [['x' => 1, 'y' => 2]];
        yield 'map nested in a list' => [[['x' => 1], ['x' => 2]]];
        yield 'list nested in a map' => [['items' => ['a', 'b'], 'count' => 2]];
        yield 'mixed depth' => [['a' => ['b' => [1, ['c' => null]]]]];
    }

    /**
     * @return iterable<string, array{float, string}>
     */
    public static function nonFiniteFloatProvider(): iterable
    {
        yield 'NAN' => [\NAN, 'NAN'];
        yield 'INF' => [\INF, 'INF'];
        yield 'negative INF' => [-\INF, '-INF'];
    }

    /**
     * @return iterable<string, array{StoredValue, StoredValue, bool}>
     */
    public static function equalityProvider(): iterable
    {
        yield 'identical strings' => [StoredValue::ofString('a'), StoredValue::ofString('a'), true];
        yield 'different strings' => [StoredValue::ofString('a'), StoredValue::ofString('b'), false];
        yield 'int zero and string zero' => [StoredValue::ofInt(0), StoredValue::ofString('0'), false];
        yield 'int zero and false' => [StoredValue::ofInt(0), StoredValue::ofBool(false), false];
        yield 'string zero and false' => [StoredValue::ofString('0'), StoredValue::ofBool(false), false];
        yield 'int one and float one' => [StoredValue::ofInt(1), StoredValue::ofFloat(1.0), false];
        yield 'null and null' => [StoredValue::ofNull(), StoredValue::ofNull(), true];
        yield 'null and empty string' => [StoredValue::ofNull(), StoredValue::ofString(''), false];
        yield 'lists in the same order' => [
            StoredValue::ofList([StoredValue::ofString('a'), StoredValue::ofString('b')]),
            StoredValue::ofList([StoredValue::ofString('a'), StoredValue::ofString('b')]),
            true,
        ];
        yield 'lists in a different order' => [
            StoredValue::ofList([StoredValue::ofString('a'), StoredValue::ofString('b')]),
            StoredValue::ofList([StoredValue::ofString('b'), StoredValue::ofString('a')]),
            false,
        ];
        yield 'lists of different length' => [
            StoredValue::ofList([StoredValue::ofString('a')]),
            StoredValue::ofList([StoredValue::ofString('a'), StoredValue::ofString('b')]),
            false,
        ];
        yield 'maps with the same entries in a different key order' => [
            StoredValue::ofMap(['x' => StoredValue::ofInt(1), 'y' => StoredValue::ofInt(2)]),
            StoredValue::ofMap(['y' => StoredValue::ofInt(2), 'x' => StoredValue::ofInt(1)]),
            true,
        ];
        yield 'maps with a differing value' => [
            StoredValue::ofMap(['x' => StoredValue::ofInt(1)]),
            StoredValue::ofMap(['x' => StoredValue::ofInt(2)]),
            false,
        ];
        yield 'maps with different keys' => [
            StoredValue::ofMap(['x' => StoredValue::ofInt(1)]),
            StoredValue::ofMap(['y' => StoredValue::ofInt(1)]),
            false,
        ];
        yield 'empty list and empty map' => [StoredValue::ofList([]), StoredValue::ofMap([]), false];
        yield 'nested maps compared recursively' => [
            StoredValue::ofMap(['a' => StoredValue::ofList([StoredValue::ofMap(['b' => StoredValue::ofString('c')])])]),
            StoredValue::ofMap(['a' => StoredValue::ofList([StoredValue::ofMap(['b' => StoredValue::ofString('c')])])]),
            true,
        ];
        yield 'nested maps differing at depth' => [
            StoredValue::ofMap(['a' => StoredValue::ofList([StoredValue::ofMap(['b' => StoredValue::ofString('c')])])]),
            StoredValue::ofMap(['a' => StoredValue::ofList([StoredValue::ofMap(['b' => StoredValue::ofString('d')])])]),
            false,
        ];
    }

    #[DataProvider('variantConstructorProvider')]
    #[TestDox('unwraps back to the payload it wrapped')]
    public function testVariantConstructorRoundTripsItsPayload(StoredValue $value, mixed $expected): void
    {
        static::assertSame($expected, $value->jsonSerialize());
    }

    #[DataProvider('matchingScalarAccessorProvider')]
    #[TestDox('returns the payload on a variant match')]
    public function testScalarAccessorReturnsThePayloadOnAVariantMatch(
        StoredValue $value,
        callable $accessor,
        string|int|float|bool $expected
    ): void {
        static::assertSame($expected, $accessor($value));
    }

    #[TestDox('returns the wrapped elements of a list variant')]
    public function testAsListReturnsTheWrappedElements(): void
    {
        $first = StoredValue::ofString('a');
        $second = StoredValue::ofString('b');

        static::assertSame([$first, $second], StoredValue::ofList([$first, $second])->asList());
    }

    #[TestDox('returns the wrapped entries of a map variant keyed as authored')]
    public function testAsMapReturnsTheWrappedEntriesByKey(): void
    {
        $value = StoredValue::ofString('a');

        static::assertSame(['x' => $value], StoredValue::ofMap(['x' => $value])->asMap());
    }

    #[DataProvider('nullVariantProvider')]
    #[TestDox('returns true only for the null variant, never for a falsy payload')]
    public function testIsNullIsTrueOnlyForTheNullVariant(StoredValue $value, bool $expected): void
    {
        static::assertSame($expected, $value->isNull());
    }

    #[DataProvider('decodableValueProvider')]
    #[TestDox('wraps a raw decoded value so it unwraps back unchanged')]
    public function testFromDecodedRoundTripsARawValue(mixed $raw): void
    {
        static::assertSame($raw, StoredValue::fromDecoded($raw)->jsonSerialize());
    }

    #[TestDox('wraps every nested value, not only the outermost one')]
    public function testFromDecodedWrapsNestedValuesAtEveryDepth(): void
    {
        $value = StoredValue::fromDecoded(['items' => ['first', 'second']]);

        static::assertSame('first', $value->asMap()['items']->asList()[0]->asString());
    }

    #[TestDox('reads a zero-indexed array as a list and a keyed array as a map')]
    public function testFromDecodedDistinguishesListsFromMaps(): void
    {
        static::assertFalse(StoredValue::fromDecoded([])->equals(StoredValue::ofMap([])));
        static::assertTrue(StoredValue::fromDecoded([])->equals(StoredValue::ofList([])));
        static::assertTrue(StoredValue::fromDecoded(['x' => 1])->equals(StoredValue::ofMap(['x' => StoredValue::ofInt(1)])));
    }

    #[DataProvider('equalityProvider')]
    #[TestDox('compares scalars strictly, lists positionally and maps regardless of key order')]
    public function testEqualsAppliesTheCanonicalComparison(StoredValue $left, StoredValue $right, bool $expected): void
    {
        static::assertSame($expected, $left->equals($right));
        static::assertSame($expected, $right->equals($left));
    }

    #[DataProvider('mismatchedAccessorProvider')]
    #[TestDox('throws when the variant does not match')]
    public function testShapeSafeAccessorThrowsOnAVariantMismatch(
        StoredValue $value,
        callable $accessor,
        ContentSystemException $expected
    ): void {
        $this->expectExceptionObject($expected);

        $accessor($value);
    }

    #[DataProvider('nonFiniteFloatProvider')]
    #[TestDox('rejects a float that cannot be JSON encoded')]
    public function testOfFloatRejectsANonFiniteFloat(float $value, string $rendered): void
    {
        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('StoredValue', 'finite float', $rendered)
        );

        StoredValue::ofFloat($value);
    }

    #[TestDox('rejects a non-finite float found below the top level')]
    public function testFromDecodedRejectsANonFiniteFloatNestedInAList(): void
    {
        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('StoredValue', 'finite float', 'INF')
        );

        StoredValue::fromDecoded(['ratios' => [1.0, \INF]]);
    }

    #[TestDox('rejects an object payload rather than coercing it')]
    public function testFromDecodedRejectsAnObjectPayload(): void
    {
        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('StoredValue', 'scalar, null or array', 'stdClass')
        );

        StoredValue::fromDecoded(new \stdClass());
    }
}
