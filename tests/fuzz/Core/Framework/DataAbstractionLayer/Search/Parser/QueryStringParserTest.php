<?php declare(strict_types=1);

namespace Shopware\Tests\Fuzz\Core\Framework\DataAbstractionLayer\Search\Parser;

use Eris\Generator;
use Eris\Generators;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\SearchRequestException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\QueryStringParser;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Fuzz\FuzzTestCase;

/**
 * Checks that serializing a parsed Filter back to an array and re-parsing it always
 * reconstructs an equal Filter (toArray() . fromArray() is a stable round trip).
 *
 * @internal
 */
#[Package('framework')]
class QueryStringParserTest extends FuzzTestCase
{
    public function testToArrayFromArrayRoundTripsToAnEquivalentFilter(): void
    {
        $definition = new ProductDefinition();

        $this->forAll($this->queryGenerator())
            ->then(function (array $query) use ($definition): void {
                $parsed = QueryStringParser::fromArray($definition, $query, new SearchRequestException());

                $reparsed = QueryStringParser::fromArray(
                    $definition,
                    QueryStringParser::toArray($parsed),
                    new SearchRequestException()
                );

                static::assertEquals($parsed, $reparsed);
            });
    }

    /**
     * Known bug, pinned rather than skipped: equalsAny round-trips values through
     * implode/explode("|", ...) with no escaping. Once QueryStringParser/EqualsAnyFilter
     * properly escape equalsAny values, both assertions below will start failing - replace
     * them with a single assertEquals($parsed, $reparsed) round trip, as in
     * testToArrayFromArrayRoundTripsToAnEquivalentFilter().
     */
    public function testEqualsAnyRoundTripHandlesPipeAndZeroValues(): void
    {
        $definition = new ProductDefinition();
        // excludes "|" so combining two of these with a literal "|" produces exactly one
        // delimiter, not an unpredictable number of them
        $safe = Generators::suchThat(
            static fn (string $s): bool => !str_contains($s, '|'),
            Generators::map(static fn (string $s): string => 'v' . $s, Generators::string())
        );

        $this->forAll(Generators::tuple($safe, $safe, $safe))
            ->then(function (array $names) use ($definition): void {
                [$keptValue, $left, $right] = $names;

                // "0" is silently dropped by array_filter() on the way back - and since
                // array_filter() preserves keys instead of reindexing, the survivor keeps
                // its original key (1), not 0.
                static::assertSame(
                    [1 => $keptValue],
                    $this->reparseEqualsAnyValue($definition, ['0', $keptValue])
                );

                // a value containing "|" is split into two values on the way back.
                static::assertSame(
                    [$left, $right],
                    $this->reparseEqualsAnyValue($definition, [$left . '|' . $right])
                );
            });
    }

    /**
     * @param list<string> $values
     *
     * @return array<int|string, bool|float|int|string|null> not a list: array_filter()
     *                                                       preserves keys, so a dropped "0"
     *                                                       value leaves a gap instead of
     *                                                       reindexing
     */
    private function reparseEqualsAnyValue(ProductDefinition $definition, array $values): array
    {
        $parsed = QueryStringParser::fromArray(
            $definition,
            ['type' => 'equalsAny', 'field' => 'name', 'value' => $values],
            new SearchRequestException()
        );

        $reparsed = QueryStringParser::fromArray(
            $definition,
            QueryStringParser::toArray($parsed),
            new SearchRequestException()
        );

        static::assertInstanceOf(EqualsAnyFilter::class, $reparsed);

        return $reparsed->getValue();
    }

    /**
     * @phpstan-ignore missingType.generics (Eris's Generator only declares a Psalm template, not a PHPStan-compatible one)
     */
    private function queryGenerator(): Generator
    {
        $field = Generators::elements('name', 'active', 'stock');
        // prefixed so it's always non-empty (required by "equals"/"contains"/"prefix"/"suffix")
        // and can never equal the literal string "0" - which matters below.
        $value = Generators::map(static fn (string $s): string => 'v' . $s, Generators::string());

        // TODO: known bug, see testEqualsAnyRoundTripHandlesPipeAndZeroValues(). Excluded
        // here so this test stays green; delete once fixed and use $value directly.
        $equalsAnyValue = Generators::suchThat(static fn (string $s): bool => !str_contains($s, '|'), $value);

        $leaf = static fn (string $type) => Generators::map(
            static fn (array $pair): array => ['type' => $type, 'field' => $pair[0], 'value' => $pair[1]],
            Generators::tuple($field, $value)
        );

        $rangeLeaf = Generators::map(
            static fn (array $pair): array => ['type' => 'range', 'field' => $pair[0], 'parameters' => [RangeFilter::GT => $pair[1]]],
            Generators::tuple($field, $value)
        );

        $equalsAnyLeaf = Generators::map(
            static fn (array $pair): array => ['type' => 'equalsAny', 'field' => $pair[0], 'value' => $pair[1]],
            Generators::tuple($field, Generators::vector(2, $equalsAnyValue))
        );

        $leafFilter = Generators::oneOf(
            $leaf('equals'),
            $leaf('contains'),
            $leaf('prefix'),
            $leaf('suffix'),
            $rangeLeaf,
            $equalsAnyLeaf,
        );

        $operator = Generators::elements('AND', 'OR');

        $multi = Generators::map(
            static fn (array $triple): array => ['type' => 'multi', 'operator' => $triple[0], 'queries' => [$triple[1], $triple[2]]],
            Generators::tuple($operator, $leafFilter, $leafFilter)
        );

        $not = Generators::map(
            static fn (array $pair): array => ['type' => 'not', 'operator' => $pair[0], 'queries' => [$pair[1]]],
            Generators::tuple($operator, $leafFilter)
        );

        return Generators::oneOf($leafFilter, $multi, $not);
    }
}
