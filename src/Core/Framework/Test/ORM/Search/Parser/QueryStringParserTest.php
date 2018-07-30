<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\ORM\Search\Parser;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\ORM\Exception\InvalidFilterQueryException;
use Shopware\Core\Framework\ORM\Exception\SearchRequestException;
use Shopware\Core\Framework\ORM\Search\Parser\QueryStringParser;
use Shopware\Core\Framework\ORM\Search\Query\MatchQuery;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Shopware\Core\Framework\ORM\Search\Query\TermsQuery;

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

        $this->assertInstanceOf(TermQuery::class, $result);

        $this->assertEquals($result->getField(), 'product.' . $filter['field']);
        $this->assertEquals($result->getValue(), $filter['value']);
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

        $this->assertInstanceOf(MatchQuery::class, $result);

        $this->assertEquals($result->getField(), 'product.' . $filter['field']);
        $this->assertEquals($result->getValue(), $filter['value']);
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
     * @dataProvider termsQueryDataProvider
     *
     * @param array $filter
     * @param bool  $expectException
     */
    public function testTermsQuery(array $filter, bool $expectException): void
    {
        if ($expectException) {
            $this->expectException(InvalidFilterQueryException::class);
        }

        /** @var TermsQuery $result */
        $result = QueryStringParser::fromArray(ProductDefinition::class, $filter, new SearchRequestException());

        $this->assertInstanceOf(TermsQuery::class, $result);

        $expectedValue = $filter['value'];
        if (is_string($expectedValue)) {
            $expectedValue = array_filter(explode('|', $expectedValue));
        }

        if (!is_array($expectedValue)) {
            $expectedValue = [$expectedValue];
        }

        $this->assertEquals($result->getField(), 'product.' . $filter['field']);
        $this->assertEquals($result->getValue(), $expectedValue);
    }

    public function termsQueryDataProvider(): array
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
