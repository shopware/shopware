<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\PlaceholderValues;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PlaceholderValues::class)]
class PlaceholderValuesTest extends TestCase
{
    /**
     * @param array<string, string|int|bool|float> $values
     */
    #[DataProvider('createsInstanceWithScalarValuesProvider')]
    #[TestDox('creates instance with valid scalar values')]
    public function testFromCreatesInstancePreservingAllScalarValues(array $values): void
    {
        $placeholderValues = PlaceholderValues::from($values);

        static::assertSame($values, $placeholderValues->all());
    }

    #[TestDox('accepts empty array and returns empty values')]
    public function testFromAcceptsEmptyArray(): void
    {
        $placeholderValues = PlaceholderValues::from([]);

        static::assertSame([], $placeholderValues->all());
    }

    #[TestDox('throws exception when key is not a string')]
    public function testFromThrowsForNonStringKey(): void
    {
        $this->expectExceptionObject(
            ContentSystemException::invalidMapKey('PlaceholderValues', 'int')
        );

        PlaceholderValues::from([0 => 'value']); // @phpstan-ignore argument.type
    }

    /**
     * @param array<string, mixed> $values
     */
    #[DataProvider('throwsForNonScalarValueProvider')]
    #[TestDox('throws exception when value is not scalar')]
    public function testFromThrowsForNonScalarValue(array $values, string $expectedType): void
    {
        $this->expectExceptionObject(
            ContentSystemException::invalidMapValue('PlaceholderValues', 'key', 'scalar', $expectedType)
        );

        PlaceholderValues::from($values);
    }

    /**
     * A placeholder key is not purely server-derived: `Adapter/FactoryHelper/EntityLayoutResolver` and the
     * header/footer sources merge every scalar QUERY PARAMETER into this map, and `Layout/Scaffolding/StoredTreePreparer`
     * substitutes `{{<key>}}` before inline mapping expansion runs. Without this filter, a request carrying
     * `?map:product.name=…` would pre-empt the inline mapping token `{{map:product.name}}` with attacker-chosen text
     * on every element holding one.
     */
    #[TestDox('drops keys using the reserved inline mapping prefix, keeping the two namespaces disjoint')]
    public function testFromDropsReservedMappingPrefixKeys(): void
    {
        $placeholderValues = PlaceholderValues::from([
            'productId' => 'abc',
            'map:product.name' => 'injected',
            'mapper' => 'kept, because only the exact prefix is reserved',
        ]);

        static::assertSame(
            ['productId' => 'abc', 'mapper' => 'kept, because only the exact prefix is reserved'],
            $placeholderValues->all()
        );
    }

    /**
     * Dropped rather than rejected, and that distinction is the security-relevant one: throwing would let any visitor
     * take a page down with a crafted query string.
     */
    #[TestDox('does not throw for a reserved key, because the key source is client-controlled')]
    public function testFromDoesNotThrowForAReservedKey(): void
    {
        static::assertSame([], PlaceholderValues::from(['map:product.name' => 'injected'])->all());
    }

    /**
     * @return \Generator<string, array{array<string, string|int|bool|float>}>
     */
    public static function createsInstanceWithScalarValuesProvider(): \Generator
    {
        yield 'all four scalar types accepted (string, int, bool, float)' => [['name' => 'product', 'count' => 5, 'active' => false, 'price' => 1.5]];
    }

    /**
     * @return \Generator<string, array{array<string, mixed>, string}>
     */
    public static function throwsForNonScalarValueProvider(): \Generator
    {
        yield 'null is not scalar' => [['key' => null], 'null'];
    }
}
