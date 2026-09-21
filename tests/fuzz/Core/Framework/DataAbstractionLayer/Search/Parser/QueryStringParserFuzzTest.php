<?php declare(strict_types=1);

namespace Shopware\Tests\Fuzz\Core\Framework\DataAbstractionLayer\Search\Parser;

use Eris\Generator;
use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\SearchRequestException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\QueryStringParser;
use Shopware\Core\Framework\Log\Package;

/**
 * Property-based companion to QueryStringParserTest: generates filter arrays restricted to the
 * types QueryStringParser::toArray() fully supports (equals/contains/prefix/suffix/range/
 * equalsAny, plus one level of multi/not nesting over those) and checks that serializing a
 * parsed Filter back to an array and re-parsing it always reconstructs an equal Filter -
 * toArray() . fromArray() is a stable round trip on its supported domain.
 *
 * Deliberately out of scope: the "and"/"or"/"nand"/"nor" query types. fromArray() accepts them,
 * but toArray() represents the resulting AndFilter/OrFilter/NandFilter/NorFilter through their
 * parent classes (MultiFilter/NotFilter) as "multi"/"not" - a pre-existing asymmetry, not
 * something this test suite fixes. See .agents/skills/shopware-fuzz-tests for the pattern.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(QueryStringParser::class)]
class QueryStringParserFuzzTest extends TestCase
{
    use TestTrait;

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
     * @phpstan-ignore missingType.generics (Eris's Generator only declares a Psalm template, not a PHPStan-compatible one)
     */
    private function queryGenerator(): Generator
    {
        $field = Generators::elements('name', 'active', 'stock');
        // prefixed so it's always non-empty (required by "equals"/"contains"/"prefix"/"suffix")
        // and can never equal the literal string "0" - which matters below.
        $value = Generators::map(static fn (string $s): string => 'v' . $s, Generators::string());

        // TODO: QueryStringParser's equalsAny round trip is lossy - toArray() joins values with
        // "|" (implode) and fromArray() splits on "|" via explode() + array_filter(), with no
        // escaping in either direction. Verified against the current code (see the two examples
        // below); not fixed here because it's a pre-existing, real behavior change out of scope
        // for standing up this test framework.
        //   1. A value equal to the literal string "0" is silently dropped - array_filter()
        //      treats "0" as falsy:
        //        fromArray(['type' => 'equalsAny', 'field' => 'name', 'value' => ['0', 'a']])
        //          -> toArray() => ['value' => '0|a']
        //          -> fromArray() => value is [1 => 'a'] (not ['0', 'a'])
        //   2. A value containing "|" gets split into extra values on the way back:
        //        fromArray(['type' => 'equalsAny', 'field' => 'name', 'value' => ['foo|bar']])
        //          -> toArray() => ['value' => 'foo|bar']
        //          -> fromArray() => value is [0 => 'foo', 1 => 'bar']
        // Once QueryStringParser/EqualsAnyFilter properly escape equalsAny values:
        //   - delete the suchThat() line below and pass $value directly to $equalsAnyLeaf's
        //     Generators::vector(2, ...) call
        //   - uncomment testEqualsAnyRoundTripHandlesPipeAndZeroValues() at the bottom of this
        //     class to lock the fix in as a permanent regression test
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

    // Currently fails - see the TODO in queryGenerator(). Uncomment once the equalsAny
    // "|"/"0" round-trip bug is fixed, to pin the fix down as a permanent regression test.
    //
    // public function testEqualsAnyRoundTripHandlesPipeAndZeroValues(): void
    // {
    //     $definition = new ProductDefinition();
    //
    //     foreach ([['0', 'a'], ['foo|bar']] as $values) {
    //         $parsed = QueryStringParser::fromArray(
    //             $definition,
    //             ['type' => 'equalsAny', 'field' => 'name', 'value' => $values],
    //             new SearchRequestException()
    //         );
    //
    //         $reparsed = QueryStringParser::fromArray(
    //             $definition,
    //             QueryStringParser::toArray($parsed),
    //             new SearchRequestException()
    //         );
    //
    //         static::assertEquals($parsed, $reparsed);
    //     }
    // }
}
