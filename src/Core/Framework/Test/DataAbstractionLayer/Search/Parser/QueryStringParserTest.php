<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Parser;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidFilterQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\SearchRequestException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\QueryStringParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\MatchQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\TermQuery;

class QueryStringParserTest extends TestCase
{
    public function testWithUnsupportedFormat(): void
    {
        $this->expectException(InvalidFilterQueryException::class);
        QueryStringParser::fromArray(ProductDefinition::class, ['type' => 'foo'], new SearchRequestException());
    }

    public function testInvalidParameters(): void
    {
        $this->expectException(InvalidFilterQueryException::class);
        QueryStringParser::fromArray(ProductDefinition::class, ['foo' => 'bar'], new SearchRequestException());
    }

    /**
     * @dataProvider termQueryDataProvider
     *
     * @param array $filter
     * @param bool  $expectException
     */
    public function testTermQuery(array $filter, bool $expectException): void
    {
        if ($expectException) {
            $this->expectException(InvalidFilterQueryException::class);
        }

        /** @var TermQuery $result */
        $result = QueryStringParser::fromArray(ProductDefinition::class, $filter, new SearchRequestException());

        static::assertInstanceOf(TermQuery::class, $result);

        static::assertEquals($result->getField(), 'product.' . $filter['field']);
        static::assertEquals($result->getValue(), $filter['value']);
    }

    public function termQueryDataProvider(): array
    {
        return [
            [['type' => 'term', 'field' => 'foo', 'value' => 'bar'], false],
            [['type' => 'term', 'field' => 'foo', 'value' => ''], true],
            [['type' => 'term', 'field' => '', 'value' => 'bar'], true],
            [['type' => 'term', 'field' => 'foo'], true],
            [['type' => 'term', 'value' => 'bar'], true],
            [['type' => 'term', 'field' => 'foo', 'value' => true], false],
            [['type' => 'term', 'field' => 'foo', 'value' => false], false],
            [['type' => 'term', 'field' => 'foo', 'value' => 1], false],
            [['type' => 'term', 'field' => 'foo', 'value' => 0], false],
        ];
    }

    /**
     * @dataProvider matchQueryDataProvider
     *
     * @param array $filter
     * @param bool  $expectException
     */
    public function testMatchQuery(array $filter, bool $expectException): void
    {
        if ($expectException) {
            $this->expectException(InvalidFilterQueryException::class);
        }

        /** @var TermQuery $result */
        $result = QueryStringParser::fromArray(ProductDefinition::class, $filter, new SearchRequestException());

        static::assertInstanceOf(MatchQuery::class, $result);

        static::assertEquals($result->getField(), 'product.' . $filter['field']);
        static::assertEquals($result->getValue(), $filter['value']);
    }

    public function matchQueryDataProvider(): array
    {
        return [
            [['type' => 'match', 'field' => 'foo', 'value' => 'bar'], false],
            [['type' => 'match', 'field' => 'foo', 'value' => ''], true],
            [['type' => 'match', 'field' => '', 'value' => 'bar'], true],
            [['type' => 'match', 'field' => 'foo'], true],
            [['type' => 'match', 'value' => 'bar'], true],
            [['type' => 'match', 'field' => 'foo', 'value' => true], false],
            [['type' => 'match', 'field' => 'foo', 'value' => false], false],
            [['type' => 'match', 'field' => 'foo', 'value' => 1], false],
            [['type' => 'match', 'field' => 'foo', 'value' => 0], false],
        ];
    }

    /**
     * @dataProvider equalsAnyFilterDataProvider
     *
     * @param array $filter
     * @param bool  $expectException
     */
    public function testEqualsAnyFilter(array $filter, bool $expectException): void
    {
        if ($expectException) {
            $this->expectException(InvalidFilterQueryException::class);
        }

        /** @var EqualsAnyFilter $result */
        $result = QueryStringParser::fromArray(ProductDefinition::class, $filter, new SearchRequestException());

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
            [['type' => 'terms', 'field' => 'foo', 'value' => 'bar'], false],
            [['type' => 'terms', 'field' => 'foo', 'value' => ''], true],
            [['type' => 'terms', 'field' => '', 'value' => 'bar'], true],
            [['type' => 'terms', 'field' => 'foo', 'value' => 'abc|def|ghi'], false],
            [['type' => 'terms', 'field' => 'foo', 'value' => 'false|true|0'], false],
            [['type' => 'terms', 'field' => 'foo'], true],
            [['type' => 'terms', 'value' => 'foo'], true],
            [['type' => 'terms', 'field' => 'foo', 'value' => '||||'], true],
            [['type' => 'terms', 'field' => 'foo', 'value' => true], false],
            [['type' => 'terms', 'field' => 'foo', 'value' => false], true],
            [['type' => 'terms', 'field' => 'foo', 'value' => 0], true],
            [['type' => 'terms', 'field' => 'foo', 'value' => 1], false],
        ];
    }
}
