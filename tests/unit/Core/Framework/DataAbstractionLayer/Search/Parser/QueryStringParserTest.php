<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Search\Parser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\SearchRequestException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NandFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NorFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\SuffixFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\QueryStringParser;

/**
 * @internal
 *
 * @phpstan-import-type EqualsFilterType from QueryStringParser
 * @phpstan-import-type NotFilterType from QueryStringParser
 * @phpstan-import-type MultiFilterType from QueryStringParser
 * @phpstan-import-type ContainsFilterType from QueryStringParser
 * @phpstan-import-type PrefixFilterType from QueryStringParser
 * @phpstan-import-type SuffixFilterType from QueryStringParser
 * @phpstan-import-type RangeFilterType from QueryStringParser
 * @phpstan-import-type EqualsAnyFilterType from QueryStringParser
 * @phpstan-import-type Query from QueryStringParser
 */
#[CoversClass(QueryStringParser::class)]
class QueryStringParserTest extends TestCase
{
    public function testWithUnsupportedFormat(): void
    {
        $this->expectException(DataAbstractionLayerException::class);
        QueryStringParser::fromArray(new ProductDefinition(), ['type' => 'foo'], new SearchRequestException());
    }

    public function testInvalidParameters(): void
    {
        $this->expectException(DataAbstractionLayerException::class);
        QueryStringParser::fromArray(new ProductDefinition(), ['foo' => 'bar'], new SearchRequestException());
    }

    /**
     * @param Query $payload
     */
    #[DataProvider('parserProvider')]
    public function testParser(array $payload, Filter $expected): void
    {
        $result = QueryStringParser::fromArray(
            new ProductDefinition(),
            $payload,
            new SearchRequestException()
        );

        static::assertEquals($expected, $result);
    }

    public static function parserProvider(): \Generator
    {
        yield 'Test and filter' => [
            ['type' => 'and', 'queries' => [['type' => 'equals', 'field' => 'name', 'value' => 'foo']]],
            new AndFilter([new EqualsFilter('product.name', 'foo')]),
        ];
        yield 'Test null queries' => [
            ['type' => 'and', 'queries' => null],
            new AndFilter(),
        ];
        yield 'Test without queries' => [
            ['type' => 'and'],
            new AndFilter(),
        ];
        yield 'Test or filter' => [
            ['type' => 'or', 'queries' => [['type' => 'equals', 'field' => 'name', 'value' => 'foo']]],
            new OrFilter([new EqualsFilter('product.name', 'foo')]),
        ];
        yield 'Test nor filter' => [
            ['type' => 'nor', 'queries' => [['type' => 'equals', 'field' => 'name', 'value' => 'foo']]],
            new NorFilter([new EqualsFilter('product.name', 'foo')]),
        ];
        yield 'Test nand filter' => [
            ['type' => 'nand', 'queries' => [['type' => 'equals', 'field' => 'name', 'value' => 'foo']]],
            new NandFilter([new EqualsFilter('product.name', 'foo')]),
        ];
    }

    /**
     * @param EqualsFilterType $filter
     */
    #[DataProvider('equalsFilterDataProvider')]
    public function testEqualsFilter(array $filter, bool $expectException): void
    {
        if ($expectException) {
            $this->expectException(DataAbstractionLayerException::class);
        }

        $result = QueryStringParser::fromArray(new ProductDefinition(), $filter, new SearchRequestException());

        static::assertInstanceOf(EqualsFilter::class, $result);
        static::assertArrayHasKey('field', $filter);
        static::assertArrayHasKey('value', $filter);

        static::assertSame($result->getField(), 'product.' . $filter['field']);
        static::assertSame($result->getValue(), $filter['value']);
    }

    public static function equalsFilterDataProvider(): \Generator
    {
        yield [['type' => 'equals', 'field' => 'foo', 'value' => 'bar'], false];
        yield [['type' => 'equals', 'field' => 'foo', 'value' => ''], true];
        yield [['type' => 'equals', 'field' => '', 'value' => 'bar'], true];
        yield [['type' => 'equals', 'field' => 'foo'], true];
        yield [['type' => 'equals', 'value' => 'bar'], true];
        yield [['type' => 'equals', 'field' => 'foo', 'value' => true], false];
        yield [['type' => 'equals', 'field' => 'foo', 'value' => false], false];
        yield [['type' => 'equals', 'field' => 'foo', 'value' => 1], false];
        yield [['type' => 'equals', 'field' => 'foo', 'value' => 1.0], false];
        yield [['type' => 'equals', 'field' => 'foo', 'value' => 0], false];
        yield [['type' => 'equals', 'field' => 'foo', 'value' => []], true];
        yield [['type' => 'equals', 'field' => 'foo', 'value' => new \stdClass()], true];
        yield [['type' => 'equals', 'field' => 'foo', 'value' => null], false];
    }

    /**
     * @param ContainsFilterType $filter
     */
    #[DataProvider('containsFilterDataProvider')]
    public function testContainsFilter(array $filter, bool $expectException): void
    {
        if ($expectException) {
            $this->expectException(DataAbstractionLayerException::class);
        }

        $result = QueryStringParser::fromArray(new ProductDefinition(), $filter, new SearchRequestException());

        static::assertInstanceOf(ContainsFilter::class, $result);
        static::assertArrayHasKey('field', $filter);
        static::assertArrayHasKey('value', $filter);

        static::assertSame($result->getField(), 'product.' . $filter['field']);
        static::assertSame($result->getValue(), $filter['value']);
    }

    public static function containsFilterDataProvider(): \Generator
    {
        yield [['type' => 'contains', 'field' => 'foo', 'value' => 'bar'], false];
        yield [['type' => 'contains', 'field' => 'foo', 'value' => ''], true];
        yield [['type' => 'contains', 'field' => '', 'value' => 'bar'], true];
        yield [['type' => 'contains', 'field' => 'foo'], true];
        yield [['type' => 'contains', 'value' => 'bar'], true];
        yield [['type' => 'contains', 'field' => 'foo', 'value' => true], false];
        yield [['type' => 'contains', 'field' => 'foo', 'value' => false], false];
        yield [['type' => 'contains', 'field' => 'foo', 'value' => 1], false];
        yield [['type' => 'contains', 'field' => 'foo', 'value' => 0], false];
    }

    /**
     * @param PrefixFilterType $filter
     */
    #[DataProvider('prefixFilterDataProvider')]
    public function testPrefixFilter(array $filter, bool $expectException): void
    {
        if ($expectException) {
            $this->expectException(DataAbstractionLayerException::class);
        }

        $result = QueryStringParser::fromArray(new ProductDefinition(), $filter, new SearchRequestException());

        static::assertInstanceOf(PrefixFilter::class, $result);
        static::assertArrayHasKey('field', $filter);
        static::assertArrayHasKey('value', $filter);

        static::assertSame($result->getField(), 'product.' . $filter['field']);
        static::assertSame($result->getValue(), (string) $filter['value']);
    }

    public static function prefixFilterDataProvider(): \Generator
    {
        yield [['type' => 'prefix', 'field' => 'foo', 'value' => 'bar'], false];
        yield [['type' => 'prefix', 'field' => 'foo', 'value' => ''], true];
        yield [['type' => 'prefix', 'field' => '', 'value' => 'bar'], true];
        yield [['type' => 'prefix', 'field' => 'foo'], true];
        yield [['type' => 'prefix', 'value' => 'bar'], true];
        yield [['type' => 'prefix', 'field' => 'foo', 'value' => true], false];
        yield [['type' => 'prefix', 'field' => 'foo', 'value' => false], false];
        yield [['type' => 'prefix', 'field' => 'foo', 'value' => 1], false];
        yield [['type' => 'prefix', 'field' => 'foo', 'value' => 0], false];
    }

    /**
     * @param SuffixFilterType $filter
     */
    #[DataProvider('suffixFilterDataProvider')]
    public function testSuffixFilter(array $filter, bool $expectException): void
    {
        if ($expectException) {
            $this->expectException(DataAbstractionLayerException::class);
        }

        $result = QueryStringParser::fromArray(new ProductDefinition(), $filter, new SearchRequestException());

        static::assertInstanceOf(SuffixFilter::class, $result);
        static::assertArrayHasKey('field', $filter);
        static::assertArrayHasKey('value', $filter);

        static::assertSame($result->getField(), 'product.' . $filter['field']);
        static::assertSame($result->getValue(), (string) $filter['value']);
    }

    public static function suffixFilterDataProvider(): \Generator
    {
        yield [['type' => 'suffix', 'field' => 'foo', 'value' => 'bar'], false];
        yield [['type' => 'suffix', 'field' => 'foo', 'value' => ''], true];
        yield [['type' => 'suffix', 'field' => '', 'value' => 'bar'], true];
        yield [['type' => 'suffix', 'field' => 'foo'], true];
        yield [['type' => 'suffix', 'value' => 'bar'], true];
        yield [['type' => 'suffix', 'field' => 'foo', 'value' => true], false];
        yield [['type' => 'suffix', 'field' => 'foo', 'value' => false], false];
        yield [['type' => 'suffix', 'field' => 'foo', 'value' => 1], false];
        yield [['type' => 'suffix', 'field' => 'foo', 'value' => 0], false];
    }

    /**
     * @param EqualsAnyFilterType $filter
     */
    #[DataProvider('equalsAnyFilterDataProvider')]
    public function testEqualsAnyFilter(array $filter, bool $expectException): void
    {
        if ($expectException) {
            $this->expectException(DataAbstractionLayerException::class);
        }

        $result = QueryStringParser::fromArray(new ProductDefinition(), $filter, new SearchRequestException());

        static::assertInstanceOf(EqualsAnyFilter::class, $result);

        static::assertArrayHasKey('field', $filter);
        static::assertArrayHasKey('value', $filter);

        $expectedValue = $filter['value'];
        if (\is_string($expectedValue)) {
            $expectedValue = array_filter(explode('|', $expectedValue));
        }

        if (!\is_array($expectedValue)) {
            $expectedValue = [$expectedValue];
        }

        static::assertSame($result->getField(), 'product.' . $filter['field']);
        static::assertSame($result->getValue(), $expectedValue);
    }

    public static function equalsAnyFilterDataProvider(): \Generator
    {
        yield [['type' => 'equalsAny', 'field' => 'foo', 'value' => 'bar'], false];
        yield [['type' => 'equalsAny', 'field' => 'foo', 'value' => ''], true];
        yield [['type' => 'equalsAny', 'field' => '', 'value' => 'bar'], true];
        yield [['type' => 'equalsAny', 'field' => 'foo', 'value' => 'abc|def|ghi'], false];
        yield [['type' => 'equalsAny', 'field' => 'foo', 'value' => 'false|true|0'], false];
        yield [['type' => 'equalsAny', 'field' => 'foo'], true];
        yield [['type' => 'equalsAny', 'value' => 'foo'], true];
        yield [['type' => 'equalsAny', 'field' => 'foo', 'value' => '||||'], true];
        yield [['type' => 'equalsAny', 'field' => 'foo', 'value' => true], false];
        yield [['type' => 'equalsAny', 'field' => 'foo', 'value' => false], true];
        yield [['type' => 'equalsAny', 'field' => 'foo', 'value' => 0], true];
        yield [['type' => 'equalsAny', 'field' => 'foo', 'value' => 1], false];
    }

    /**
     * @param EqualsAnyFilterType $filter
     */
    #[DataProvider('equalsAllFilterDataProvider')]
    public function testEqualsAllFilter(array $filter, ?Filter $expectedFilter, bool $expectException): void
    {
        if ($expectException) {
            $this->expectException(DataAbstractionLayerException::class);
        }

        $result = QueryStringParser::fromArray(new ProductDefinition(), $filter, new SearchRequestException());

        static::assertEquals($expectedFilter, $result);
    }

    public static function equalsAllFilterDataProvider(): \Generator
    {
        yield 'With empty value' => [['type' => 'equalsAll', 'field' => 'foo', 'value' => ''], null, true];
        yield 'With empty field' => [['type' => 'equalsAll', 'field' => '', 'value' => 'bar'], null, true];
        yield 'With multiple empty values' => [['type' => 'equalsAll', 'field' => 'foo', 'value' => '||||'], null, true];

        yield 'Without value key' => [['type' => 'equalsAll', 'field' => 'foo'], null, true];
        yield 'Without field key' => [['type' => 'equalsAll', 'value' => 'foo'], null, true];

        yield 'Only one string value' => [
            ['type' => 'equalsAll', 'field' => 'foo', 'value' => 'bar'],
            (new AndFilter())
                ->addQuery((new AndFilter())->addQuery(new EqualsFilter('product.foo', 'bar'))),
            false,
        ];

        yield 'With multiple string values' => [
            ['type' => 'equalsAll', 'field' => 'foo', 'value' => 'abc|def|ghi'],
            (new AndFilter())
                ->addQuery((new AndFilter())->addQuery(new EqualsFilter('product.foo', 'abc')))
                ->addQuery((new AndFilter())->addQuery(new EqualsFilter('product.foo', 'def')))
                ->addQuery((new AndFilter())->addQuery(new EqualsFilter('product.foo', 'ghi'))),
            false,
        ];

        yield 'With false, true and 0 as string values' => [
            ['type' => 'equalsAll', 'field' => 'foo', 'value' => 'false|true|0'],
            (new AndFilter())
                ->addQuery((new AndFilter())->addQuery(new EqualsFilter('product.foo', 'false')))
                ->addQuery((new AndFilter())->addQuery(new EqualsFilter('product.foo', 'true'))),
            false,
        ];

        yield 'With true as bool value' => [
            ['type' => 'equalsAll', 'field' => 'foo', 'value' => true],
            (new AndFilter())
                ->addQuery((new AndFilter())->addQuery(new EqualsFilter('product.foo', true))),
            false,
        ];

        yield 'With false as bool value' => [['type' => 'equalsAll', 'field' => 'foo', 'value' => false], null, true];
        yield 'With 0 as int value' => [['type' => 'equalsAll', 'field' => 'foo', 'value' => 0], null, true];

        yield 'With 1 as int value' => [
            ['type' => 'equalsAll', 'field' => 'foo', 'value' => 1],
            (new AndFilter())
                ->addQuery((new AndFilter())->addQuery(new EqualsFilter('product.foo', 1))),
            false,
        ];
    }

    /**
     * @param RangeFilterType $filter
     */
    #[DataProvider('rangeFilterDataProvider')]
    public function testRangeFilter(array $filter, ?Filter $expectedFilter, bool $expectException): void
    {
        if ($expectException) {
            $this->expectException(DataAbstractionLayerException::class);
        }

        $result = QueryStringParser::fromArray(new ProductDefinition(), $filter, new SearchRequestException());

        static::assertEquals($expectedFilter, $result);
    }

    public static function rangeFilterDataProvider(): \Generator
    {
        yield 'With empty value' => [['type' => 'range', 'field' => 'foo', 'parameters' => ['lte' => '']], null, true];

        yield 'With empty parameters' => [['type' => 'range', 'field' => 'foo', 'parameters' => []], null, true];

        yield 'With not suppoerted parameter key' => [['type' => 'range', 'field' => 'foo', 'parameters' => ['foo' => 3]], null, true];

        yield 'With one parameter' => [['type' => 'range', 'field' => 'foo', 'parameters' => ['lte' => 3.0]], new RangeFilter('product.foo', [RangeFilter::LTE => 3.0]), false];

        yield 'With multiple parameter' => [['type' => 'range', 'field' => 'foo', 'parameters' => ['lte' => 3.0, 'gte' => 1.5]], new RangeFilter('product.foo', [RangeFilter::LTE => 3.0, RangeFilter::GTE => 1.5]), false];
    }

    /**
     * @param RangeFilterType $filter
     */
    #[DataProvider('relativeTimeToDateFilterDataProvider')]
    public function testRelativeTimeToDateFilters(
        array $filter,
        bool $expectException,
        ?string $secondaryRangeOperator = null,
        bool $thresholdInFuture = true
    ): void {
        if ($expectException) {
            $this->expectException(DataAbstractionLayerException::class);
        }

        $result = QueryStringParser::fromArray(new ProductDefinition(), $filter, new SearchRequestException());

        static::assertInstanceOf(MultiFilter::class, $result);

        static::assertArrayHasKey('parameters', $filter);
        $primaryOperator = $filter['parameters']['operator'];
        $primaryQuery = $result->getQueries()[0];
        if ($primaryOperator === 'neq') {
            static::assertInstanceOf(NotFilter::class, $primaryQuery);
            $primaryQuery = $primaryQuery->getQueries()[0];
        }

        static::assertInstanceOf(RangeFilter::class, $primaryQuery);

        static::assertInstanceOf(RangeFilter::class, $result->getQueries()[1]);
        static::assertArrayHasKey('field', $filter);
        static::assertCount(2, $result->getFields());
        static::assertSame($primaryQuery->getField(), 'product.' . $filter['field']);
        static::assertSame($primaryQuery->getField(), 'product.' . $filter['field']);

        static::assertContains($secondaryRangeOperator, array_keys($result->getQueries()[1]->getParameters()));

        $now = (new \DateTimeImmutable())->setTime(0, 0, 0);

        if ($primaryOperator === 'eq' || $primaryOperator === 'neq') {
            static::assertArrayHasKey('value', $filter);
            $then = new \DateTimeImmutable();

            static::assertArrayHasKey('value', $filter);

            $dateInterval = new \DateInterval($filter['value']);
            if ($filter['type'] === 'since') {
                $dateInterval->invert = 1;
            }
            $start = $then->add($dateInterval)->setTime(0, 0, 0)->format(Defaults::STORAGE_DATE_TIME_FORMAT);
            $end = $then->add($dateInterval)->setTime(23, 59, 59)->format(Defaults::STORAGE_DATE_TIME_FORMAT);

            static::assertSame($start, $primaryQuery->getParameters()[RangeFilter::GTE]);
            static::assertSame($end, $primaryQuery->getParameters()[RangeFilter::LTE]);

            return;
        }

        $thresholdDate = \DateTimeImmutable::createFromFormat(
            Defaults::STORAGE_DATE_FORMAT,
            (string) array_values($primaryQuery->getParameters())[0]
        );

        $primaryOperator = $filter['type'] === 'since' ? $this->negateOperator($primaryOperator) : $primaryOperator;
        static::assertContains($primaryOperator, array_keys($primaryQuery->getParameters()));
        static::assertSame($thresholdInFuture, $now < $thresholdDate);
    }

    public static function relativeTimeToDateFilterDataProvider(): \Generator
    {
        // test exceptions being thrown
        yield 'missing field exception' => [['type' => 'until', 'field' => '', 'value' => 'P5D', 'parameters' => ['operator' => 'gt']], true];
        yield 'missing value exception' => [['type' => 'until', 'field' => 'foo', 'value' => '', 'parameters' => ['operator' => 'gt']], true];
        yield 'missing parameters exception' => [['type' => 'until', 'field' => 'foo', 'value' => 'P5D'], true];
        // test days until
        yield 'time until gt' => [['type' => 'until', 'field' => 'foo', 'value' => 'P5D', 'parameters' => ['operator' => 'gt']], false, 'gt'];
        yield 'time until gte' => [['type' => 'until', 'field' => 'foo', 'value' => 'P5D', 'parameters' => ['operator' => 'gte']], false, 'gt'];
        yield 'time until lt' => [['type' => 'until', 'field' => 'foo', 'value' => 'P5D', 'parameters' => ['operator' => 'lt']], false, 'gt'];
        yield 'time until lte' => [['type' => 'until', 'field' => 'foo', 'value' => 'P5D', 'parameters' => ['operator' => 'lte']], false, 'gt'];
        yield 'time until eq' => [['type' => 'until', 'field' => 'foo', 'value' => 'P5D', 'parameters' => ['operator' => 'eq']], false, 'gt'];
        yield 'time until neq' => [['type' => 'until', 'field' => 'foo', 'value' => 'P5D', 'parameters' => ['operator' => 'neq']], false, 'gt'];
        // test days since
        yield 'time since lt' => [['type' => 'since', 'field' => 'foo', 'value' => 'P5D', 'parameters' => ['operator' => 'lt']], false, 'lt', false];
        yield 'time since lte' => [['type' => 'since', 'field' => 'foo', 'value' => 'P5D', 'parameters' => ['operator' => 'lte']], false, 'lt', false];
        yield 'time since gt' => [['type' => 'since', 'field' => 'foo', 'value' => 'P5D', 'parameters' => ['operator' => 'gt']], false, 'lt', false];
        yield 'time since gte' => [['type' => 'since', 'field' => 'foo', 'value' => 'P5D', 'parameters' => ['operator' => 'gte']], false, 'lt', false];
        yield 'time since eq' => [['type' => 'since', 'field' => 'foo', 'value' => 'P5D', 'parameters' => ['operator' => 'eq']], false, 'lt'];
        yield 'time since neq' => [['type' => 'since', 'field' => 'foo', 'value' => 'P5D', 'parameters' => ['operator' => 'neq']], false, 'lt'];
    }

    private function negateOperator(string $operator): string
    {
        return match ($operator) {
            RangeFilter::LT => RangeFilter::GT,
            RangeFilter::GT => RangeFilter::LT,
            RangeFilter::LTE => RangeFilter::GTE,
            RangeFilter::GTE => RangeFilter::LTE,
            default => $operator,
        };
    }
}
