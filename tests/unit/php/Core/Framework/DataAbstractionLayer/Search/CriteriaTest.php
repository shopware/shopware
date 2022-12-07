<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Search;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

/**
 * @covers \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria
 *
 * @internal
 */
class CriteriaTest extends TestCase
{
    /**
     * @dataProvider string_convert_provider
     */
    public function testStringConvert(Criteria $criteria, string $expected): void
    {
        static::assertEquals(\json_decode($expected, true), \json_decode((string) $criteria, true));
    }

    public function string_convert_provider(): \Generator
    {
        yield 'test empty' => [
            new Criteria(),
            '{"total-count-mode":0}',
        ];

        yield 'test page / limit' => [
            (new Criteria())->setLimit(10)->setOffset(10),
            '{"total-count-mode":0,"limit":10,"page":2}',
        ];

        yield 'test filter' => [
            (new Criteria())->addFilter(new EqualsFilter('foo', 'bar')),
            '{"total-count-mode":0,"filter":[{"type":"equals","field":"foo","value":"bar"}]}',
        ];

        yield 'test sorting' => [
            (new Criteria())->addSorting(new FieldSorting('foo', 'bar')),
            '{"total-count-mode":0,"sort":[{"field":"foo","naturalSorting":false,"extensions":[],"order":"bar"}]}',
        ];

        yield 'test term' => [
            (new Criteria())->setTerm('foo'),
            '{"total-count-mode":0,"term":"foo"}',
        ];

        yield 'test query' => [
            (new Criteria())->addQuery(new ScoreQuery(new EqualsFilter('foo', 'bar'), 100)),
            '{"total-count-mode":0,"query":[{"score":100.0,"query":{"type":"equals","field":"foo","value":"bar"},"scoreField":null,"extensions":[]}]}',
        ];

        yield 'test aggregation' => [
            (new Criteria())->addAggregation(new CountAggregation('foo', 'bar')),
            '{"total-count-mode":0,"aggregations":[{"name":"foo","type":"count","field":"bar"}]}',
        ];

        yield 'test grouping' => [
            (new Criteria())->addGroupField(new FieldGrouping('foo')),
            '{"total-count-mode":0,"grouping":["foo"]}',
        ];
    }
}
