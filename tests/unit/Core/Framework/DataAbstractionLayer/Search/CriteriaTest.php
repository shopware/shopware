<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Search;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\InvalidCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Criteria::class)]
class CriteriaTest extends TestCase
{
    #[DataProvider('string_convert_provider')]
    public function testStringConvert(Criteria $criteria, string $expected): void
    {
        static::assertEquals(\json_decode($expected, true, 512, \JSON_THROW_ON_ERROR), \json_decode((string) $criteria, true, 512, \JSON_THROW_ON_ERROR));
    }

    public static function string_convert_provider(): \Generator
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

    public function testConstructorDoesNotAllowEmptyIdArray(): void
    {
        static::expectException(InvalidCriteriaIdsException::class);

        try {
            new Criteria(['']);
        } catch (InvalidCriteriaIdsException $e) {
            static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
            static::assertSame(DataAbstractionLayerException::INVALID_CRITERIA_IDS, $e->getErrorCode());

            throw $e;
        }
    }

    /**
     * @param array<mixed> $ids
     */
    #[DataProvider('invalidCriteriaIdsProvider')]
    public function testInvalidIdFormatsThrowException(array $ids): void
    {
        $wasThrown = false;

        try {
            new Criteria($ids);
        } catch (InvalidCriteriaIdsException $e) {
            static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
            static::assertSame(DataAbstractionLayerException::INVALID_CRITERIA_IDS, $e->getErrorCode());

            $wasThrown = true;
        }
        static::assertTrue($wasThrown);

        $criteria = new Criteria();
        $wasThrown = false;

        try {
            $criteria->setIds($ids);
        } catch (InvalidCriteriaIdsException $e) {
            static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
            static::assertSame(DataAbstractionLayerException::INVALID_CRITERIA_IDS, $e->getErrorCode());

            $wasThrown = true;
        }
        static::assertTrue($wasThrown);
    }

    /**
     * @return iterable<string, array<mixed>>
     */
    public static function invalidCriteriaIdsProvider(): iterable
    {
        yield 'non string list' => [[123, 456]];
        yield 'non string key values' => [[[['foo'], ['bar']]]];
        yield 'non string values' => [[[['pk-1' => 123], ['pk-2' => 456]]]];
        yield 'empty list' => [[]];
    }

    /**
     * @param array<string>|array<array<string, string>> $ids
     */
    #[DataProvider('validCriteriaIdsProvider')]
    public function testValidIdFormats(array $ids): void
    {
        $criteria = new Criteria($ids);
        static::assertSame($ids, $criteria->getIds());

        /** @var Criteria<string|array<string, string>> $criteria */
        $criteria = new Criteria();
        $criteria->setIds($ids);
        static::assertSame($ids, $criteria->getIds());
    }

    /**
     * @return iterable<string, array{array<string>|array<array<string, string>>}>
     */
    public static function validCriteriaIdsProvider(): iterable
    {
        yield 'plain id list' => [['id1', 'id2']];
        yield 'multiple pks' => [[['pk-1' => 'id1.1', 'pk-2' => 'id1.2'], ['pk-1' => 'id2.1', 'pk-2' => 'id2.2']]];
    }

    public function testGetNestingLevel(): void
    {
        $criteria = new Criteria();
        static::assertSame(0, $criteria->getNestingLevel());

        $nested = $criteria->getAssociation('nested');

        static::assertSame(1, $nested->getNestingLevel());

        $nestedNested = $nested->getAssociation('nestedNested');

        static::assertSame(2, $nestedNested->getNestingLevel());
    }

    public function testNextPagesLimitIncludesNavigationWindowAndSentinel(): void
    {
        $criteria = (new Criteria())->setLimit(2);

        static::assertSame(13, $criteria->getNextPagesLimit());
    }

    public function testResetFieldsRemovesTheAllowlistFieldSelection(): void
    {
        $criteria = (new Criteria())->addFields(['id', 'name']);

        $criteria->resetFields();

        static::assertSame([], $criteria->getFields());

        // the allowlist is gone, so the mutually exclusive denylist can be used again
        $criteria->excludeFields(['description']);

        static::assertSame(['description'], $criteria->getExcludedFields());
    }

    public function testResetExcludedFieldsRemovesTheDenylistFieldSelection(): void
    {
        $criteria = (new Criteria())->excludeFields(['description']);

        $criteria->resetExcludedFields();

        static::assertSame([], $criteria->getExcludedFields());

        // the denylist is gone, so the mutually exclusive allowlist can be used again
        $criteria->addFields(['id', 'name']);

        static::assertSame(['id', 'name'], $criteria->getFields());
    }

    public function testResetFieldsAndResetExcludedFieldsDoNotAffectEachOther(): void
    {
        $criteria = (new Criteria())->addFields(['id']);
        $criteria->resetExcludedFields();

        static::assertSame(['id'], $criteria->getFields());

        $criteria = (new Criteria())->excludeFields(['description']);
        $criteria->resetFields();

        static::assertSame(['description'], $criteria->getExcludedFields());
    }
}
