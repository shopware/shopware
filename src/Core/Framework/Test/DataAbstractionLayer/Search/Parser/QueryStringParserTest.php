<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Parser;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidFilterQueryException;
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
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
class QueryStringParserTest extends TestCase
{
    use KernelTestBehaviour;

    public function testWithUnsupportedFormat(): void
    {
        $this->expectException(InvalidFilterQueryException::class);
        QueryStringParser::fromArray($this->getContainer()->get(ProductDefinition::class), ['type' => 'foo'], new SearchRequestException());
    }

    public function testInvalidParameters(): void
    {
        $this->expectException(InvalidFilterQueryException::class);
        QueryStringParser::fromArray($this->getContainer()->get(ProductDefinition::class), ['foo' => 'bar'], new SearchRequestException());
    }

    /**
     * @dataProvider parserProvider
     */
    public function testParser(array $payload, Filter $expected): void
    {
        $result = QueryStringParser::fromArray(
            $this->getContainer()->get(ProductDefinition::class),
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
     * @dataProvider equalsFilterDataProvider
     */
    public function testEqualsFilter(array $filter, bool $expectException): void
    {
        if ($expectException) {
            $this->expectException(InvalidFilterQueryException::class);
        }

        /** @var EqualsFilter $result */
        $result = QueryStringParser::fromArray($this->getContainer()->get(ProductDefinition::class), $filter, new SearchRequestException());

        static::assertInstanceOf(EqualsFilter::class, $result);

        static::assertEquals($result->getField(), 'product.' . $filter['field']);
        static::assertEquals($result->getValue(), $filter['value']);
    }

    public static function equalsFilterDataProvider(): array
    {
        return [
            [['type' => 'equals', 'field' => 'foo', 'value' => 'bar'], false],
            [['type' => 'equals', 'field' => 'foo', 'value' => ''], true],
            [['type' => 'equals', 'field' => '', 'value' => 'bar'], true],
            [['type' => 'equals', 'field' => 'foo'], true],
            [['type' => 'equals', 'value' => 'bar'], true],
            [['type' => 'equals', 'field' => 'foo', 'value' => true], false],
            [['type' => 'equals', 'field' => 'foo', 'value' => false], false],
            [['type' => 'equals', 'field' => 'foo', 'value' => 1], false],
            [['type' => 'equals', 'field' => 'foo', 'value' => 0], false],
        ];
    }

    /**
     * @dataProvider containsFilterDataProvider
     */
    public function testContainsFilter(array $filter, bool $expectException): void
    {
        if ($expectException) {
            $this->expectException(InvalidFilterQueryException::class);
        }

        /** @var EqualsFilter $result */
        $result = QueryStringParser::fromArray($this->getContainer()->get(ProductDefinition::class), $filter, new SearchRequestException());

        static::assertInstanceOf(ContainsFilter::class, $result);

        static::assertEquals($result->getField(), 'product.' . $filter['field']);
        static::assertEquals($result->getValue(), $filter['value']);
    }

    public static function containsFilterDataProvider(): array
    {
        return [
            [['type' => 'contains', 'field' => 'foo', 'value' => 'bar'], false],
            [['type' => 'contains', 'field' => 'foo', 'value' => ''], true],
            [['type' => 'contains', 'field' => '', 'value' => 'bar'], true],
            [['type' => 'contains', 'field' => 'foo'], true],
            [['type' => 'contains', 'value' => 'bar'], true],
            [['type' => 'contains', 'field' => 'foo', 'value' => true], false],
            [['type' => 'contains', 'field' => 'foo', 'value' => false], false],
            [['type' => 'contains', 'field' => 'foo', 'value' => 1], false],
            [['type' => 'contains', 'field' => 'foo', 'value' => 0], false],
        ];
    }

    /**
     * @dataProvider prefixFilterDataProvider
     */
    public function testPrefixFilter(array $filter, bool $expectException): void
    {
        if ($expectException) {
            $this->expectException(InvalidFilterQueryException::class);
        }

        /** @var EqualsFilter $result */
        $result = QueryStringParser::fromArray($this->getContainer()->get(ProductDefinition::class), $filter, new SearchRequestException());

        static::assertInstanceOf(PrefixFilter::class, $result);

        static::assertEquals($result->getField(), 'product.' . $filter['field']);
        static::assertEquals($result->getValue(), $filter['value']);
    }

    public static function prefixFilterDataProvider(): array
    {
        return [
            [['type' => 'prefix', 'field' => 'foo', 'value' => 'bar'], false],
            [['type' => 'prefix', 'field' => 'foo', 'value' => ''], true],
            [['type' => 'prefix', 'field' => '', 'value' => 'bar'], true],
            [['type' => 'prefix', 'field' => 'foo'], true],
            [['type' => 'prefix', 'value' => 'bar'], true],
            [['type' => 'prefix', 'field' => 'foo', 'value' => true], false],
            [['type' => 'prefix', 'field' => 'foo', 'value' => false], false],
            [['type' => 'prefix', 'field' => 'foo', 'value' => 1], false],
            [['type' => 'prefix', 'field' => 'foo', 'value' => 0], false],
        ];
    }

    /**
     * @dataProvider suffixFilterDataProvider
     */
    public function testSuffixFilter(array $filter, bool $expectException): void
    {
        if ($expectException) {
            $this->expectException(InvalidFilterQueryException::class);
        }

        /** @var EqualsFilter $result */
        $result = QueryStringParser::fromArray($this->getContainer()->get(ProductDefinition::class), $filter, new SearchRequestException());

        static::assertInstanceOf(SuffixFilter::class, $result);

        static::assertEquals($result->getField(), 'product.' . $filter['field']);
        static::assertEquals($result->getValue(), $filter['value']);
    }

    public static function suffixFilterDataProvider(): array
    {
        return [
            [['type' => 'suffix', 'field' => 'foo', 'value' => 'bar'], false],
            [['type' => 'suffix', 'field' => 'foo', 'value' => ''], true],
            [['type' => 'suffix', 'field' => '', 'value' => 'bar'], true],
            [['type' => 'suffix', 'field' => 'foo'], true],
            [['type' => 'suffix', 'value' => 'bar'], true],
            [['type' => 'suffix', 'field' => 'foo', 'value' => true], false],
            [['type' => 'suffix', 'field' => 'foo', 'value' => false], false],
            [['type' => 'suffix', 'field' => 'foo', 'value' => 1], false],
            [['type' => 'suffix', 'field' => 'foo', 'value' => 0], false],
        ];
    }

    /**
     * @dataProvider equalsAnyFilterDataProvider
     */
    public function testEqualsAnyFilter(array $filter, bool $expectException): void
    {
        if ($expectException) {
            $this->expectException(InvalidFilterQueryException::class);
        }

        /** @var EqualsAnyFilter $result */
        $result = QueryStringParser::fromArray($this->getContainer()->get(ProductDefinition::class), $filter, new SearchRequestException());

        static::assertInstanceOf(EqualsAnyFilter::class, $result);

        $expectedValue = $filter['value'];
        if (\is_string($expectedValue)) {
            $expectedValue = array_filter(explode('|', $expectedValue));
        }

        if (!\is_array($expectedValue)) {
            $expectedValue = [$expectedValue];
        }

        static::assertEquals($result->getField(), 'product.' . $filter['field']);
        static::assertEquals($result->getValue(), $expectedValue);
    }

    public static function equalsAnyFilterDataProvider(): array
    {
        return [
            [['type' => 'equalsAny', 'field' => 'foo', 'value' => 'bar'], false],
            [['type' => 'equalsAny', 'field' => 'foo', 'value' => ''], true],
            [['type' => 'equalsAny', 'field' => '', 'value' => 'bar'], true],
            [['type' => 'equalsAny', 'field' => 'foo', 'value' => 'abc|def|ghi'], false],
            [['type' => 'equalsAny', 'field' => 'foo', 'value' => 'false|true|0'], false],
            [['type' => 'equalsAny', 'field' => 'foo'], true],
            [['type' => 'equalsAny', 'value' => 'foo'], true],
            [['type' => 'equalsAny', 'field' => 'foo', 'value' => '||||'], true],
            [['type' => 'equalsAny', 'field' => 'foo', 'value' => true], false],
            [['type' => 'equalsAny', 'field' => 'foo', 'value' => false], true],
            [['type' => 'equalsAny', 'field' => 'foo', 'value' => 0], true],
            [['type' => 'equalsAny', 'field' => 'foo', 'value' => 1], false],
        ];
    }

    /**
     * @dataProvider equalsAllFilterDataProvider
     */
    public function testEqualsAllFilter(array $filter, ?Filter $expectedFilter, bool $expectException): void
    {
        if ($expectException) {
            $this->expectException(InvalidFilterQueryException::class);
        }

        $result = QueryStringParser::fromArray($this->getContainer()->get(ProductDefinition::class), $filter, new SearchRequestException());

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
     * @dataProvider relativeTimeToDateFilterDataProvider
     */
    public function testRelativeTimeToDateFilters(
        array $filter,
        bool $expectException,
        ?string $secondaryRangeOperator = null,
        bool $thresholdInFuture = true
    ): void {
        if ($expectException) {
            $this->expectException(InvalidFilterQueryException::class);
        }

        /** @var MultiFilter $result */
        $result = QueryStringParser::fromArray($this->getContainer()->get(ProductDefinition::class), $filter, new SearchRequestException());

        static::assertInstanceOf(MultiFilter::class, $result);

        $primaryOperator = $filter['parameters']['operator'];
        $primaryQuery = $result->getQueries()[0];
        if ($primaryOperator === 'neq') {
            static::assertInstanceOf(NotFilter::class, $primaryQuery);
            $primaryQuery = $primaryQuery->getQueries()[0];
        }

        static::assertInstanceOf(RangeFilter::class, $primaryQuery);

        static::assertInstanceOf(RangeFilter::class, $result->getQueries()[1]);

        static::assertCount(2, $result->getFields());
        static::assertEquals($primaryQuery->getField(), 'product.' . $filter['field']);
        static::assertEquals($primaryQuery->getField(), 'product.' . $filter['field']);

        static::assertContains($secondaryRangeOperator, array_keys($result->getQueries()[1]->getParameters()));

        $now = (new \DateTimeImmutable())->setTime(0, 0, 0);

        if ($primaryOperator === 'eq' || $primaryOperator === 'neq') {
            $then = new \DateTimeImmutable();
            $dateInterval = new \DateInterval($filter['value']);
            if ($filter['type'] === 'since') {
                $dateInterval->invert = 1;
            }
            $start = $then->add($dateInterval)->setTime(0, 0, 0)->format(Defaults::STORAGE_DATE_TIME_FORMAT);
            $end = $then->add($dateInterval)->setTime(23, 59, 59)->format(Defaults::STORAGE_DATE_TIME_FORMAT);

            static::assertEquals($start, $primaryQuery->getParameters()[RangeFilter::GTE]);
            static::assertEquals($end, $primaryQuery->getParameters()[RangeFilter::LTE]);

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

    public static function relativeTimeToDateFilterDataProvider(): iterable
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
