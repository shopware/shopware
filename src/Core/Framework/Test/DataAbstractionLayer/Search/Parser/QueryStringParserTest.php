<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Parser;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidFilterQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\SearchRequestException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\SuffixFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\QueryStringParser;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

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

    public function equalsFilterDataProvider(): array
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

    public function containsFilterDataProvider(): array
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

    public function prefixFilterDataProvider(): array
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

    public function suffixFilterDataProvider(): array
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

    public function equalsAnyFilterDataProvider(): array
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
        } else {
            static::assertInstanceOf(RangeFilter::class, $primaryQuery);
        }

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
            array_values($primaryQuery->getParameters())[0]
        );

        $primaryOperator = $filter['type'] === 'since' ? $this->negateOperator($primaryOperator) : $primaryOperator;
        static::assertContains($primaryOperator, array_keys($primaryQuery->getParameters()));
        static::assertSame($thresholdInFuture, $now < $thresholdDate);
    }

    public function relativeTimeToDateFilterDataProvider(): iterable
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
        switch ($operator) {
            case RangeFilter::LT:
                return RangeFilter::GT;
            case RangeFilter::GT:
                return RangeFilter::LT;
            case RangeFilter::LTE:
                return RangeFilter::GTE;
            case RangeFilter::GTE:
                return RangeFilter::LTE;
            default:
                return $operator;
        }
    }
}
