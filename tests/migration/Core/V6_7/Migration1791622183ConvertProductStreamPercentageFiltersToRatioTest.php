<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexer;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\IndexerQueuer;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1770740885AddRevisionToConsentState;
use Shopware\Core\Migration\V6_7\Migration1791622183ConvertProductStreamPercentageFiltersToRatio;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1791622183ConvertProductStreamPercentageFiltersToRatio::class)]
class Migration1791622183ConvertProductStreamPercentageFiltersToRatioTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1791622183, (new Migration1791622183ConvertProductStreamPercentageFiltersToRatio())->getCreationTimestamp());
    }

    /**
     * @param array<string, float|int|string|null>|null $parameters
     * @param array<string, float|int|null>|null $expectedParameters
     */
    #[DataProvider('discountFilterProvider')]
    public function testConvertsConditionsWrittenBeforeTheRatioRelease(
        string $field,
        string $type,
        ?string $value,
        ?array $parameters,
        ?string $expectedValue,
        ?array $expectedParameters,
    ): void {
        $ratioSince = $this->getRatioSince();
        $filterId = $this->createFilter($field, $type, $value, $parameters, $ratioSince->modify('-1 day'));
        $otherFilterId = $this->createFilter('stock', 'range', null, ['gte' => 10], $ratioSince->modify('-1 day'));

        $migration = new Migration1791622183ConvertProductStreamPercentageFiltersToRatio();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $filter = $this->fetchFilter($filterId);
        static::assertSame($expectedValue, $filter['value']);
        static::assertEquals($expectedParameters, $filter['parameters']);
        static::assertNotNull($filter['updated_at']);
        static::assertGreaterThan($ratioSince, new \DateTimeImmutable($filter['updated_at']));

        $otherFilter = $this->fetchFilter($otherFilterId);
        static::assertEquals(['gte' => 10], $otherFilter['parameters']);
        static::assertNull($otherFilter['updated_at']);
    }

    /**
     * @return iterable<string, array{string, string, string|null, array<string, float|int|string|null>|null, string|null, array<string, float|int|null>|null}>
     */
    public static function discountFilterProvider(): iterable
    {
        yield 'at least 10 % off' => ['cheapestPrice.percentage', 'range', null, ['gte' => 10], null, ['lte' => 90]];
        yield 'any discount' => ['cheapestPrice.percentage', 'range', null, ['gte' => 0.1], null, ['lte' => 99.9]];
        yield 'more than 1 % off' => ['cheapestPrice.percentage', 'range', null, ['gt' => 1], null, ['lt' => 99]];
        yield 'less than 50 % off' => ['cheapestPrice.percentage', 'range', null, ['lt' => 50], null, ['gt' => 50]];
        yield 'at most 99.9 % off' => ['cheapestPrice.percentage', 'range', null, ['lte' => 99.9], null, ['gte' => 0.1]];
        yield 'between 10 and 50 % off' => ['cheapestPrice.percentage', 'range', null, ['gte' => 10, 'lte' => 50], null, ['gte' => 50, 'lte' => 90]];
        yield 'at least 100 % off' => ['cheapestPrice.percentage', 'range', null, ['gte' => 100], null, ['lte' => 0]];
        yield 'at least 10 % off, up to 100 %' => ['cheapestPrice.percentage', 'range', null, ['gte' => 10, 'lte' => 100], null, ['gte' => 0, 'lte' => 90]];
        yield 'range with an empty bound' => ['cheapestPrice.percentage', 'range', null, ['gte' => 10, 'lte' => null], null, ['gte' => null, 'lte' => 90]];
        yield 'numeric string bound' => ['cheapestPrice.percentage', 'range', null, ['gte' => '12.5'], null, ['lte' => 87.5]];
        yield 'exactly 25 % off' => ['cheapestPrice.percentage', 'equals', '25', null, '75', null];
        yield 'any of 10 or 25 % off' => ['cheapestPrice.percentage', 'equalsAny', '10|25', null, '90|75', null];
        yield 'entity prefixed field' => ['product.cheapestPrice.percentage', 'range', null, ['gte' => 10], null, ['lte' => 90]];
    }

    /**
     * @param array<string, float|int|null> $parameters
     */
    #[DataProvider('ratioFilterProvider')]
    public function testKeepsConditionsWrittenForTheRatioBeforeTheRatioRelease(string $field, array $parameters): void
    {
        $this->resetScheduledIndexers();
        $filterId = $this->createFilter($field, 'range', null, $parameters, $this->getRatioSince()->modify('-1 day'));
        $before = $this->fetchRawFilter($filterId);

        (new Migration1791622183ConvertProductStreamPercentageFiltersToRatio())->update($this->connection);

        static::assertSame($before, $this->fetchRawFilter($filterId));
        static::assertSame([], (new IndexerQueuer($this->connection))->getIndexers());
    }

    /**
     * @return iterable<string, array{string, array<string, float|int|null>}>
     */
    public static function ratioFilterProvider(): iterable
    {
        yield 'less than 100' => ['cheapestPrice.percentage', ['lt' => 100]];
        yield 'at most 100' => ['cheapestPrice.percentage', ['lte' => 100]];
        yield 'less than 120' => ['cheapestPrice.percentage', ['lt' => 120]];
        yield 'less than 100 with an empty lower bound' => ['cheapestPrice.percentage', ['gte' => null, 'lt' => 100]];
        yield 'entity prefixed field' => ['product.cheapestPrice.percentage', ['lt' => 100]];
    }

    public function testKeepsConditionsWrittenOrEditedSinceTheRatioRelease(): void
    {
        $this->resetScheduledIndexers();
        $ratioSince = $this->getRatioSince();
        $createdSince = $this->createFilter('cheapestPrice.percentage', 'range', null, ['lt' => 100], $ratioSince->modify('+1 day'));
        $editedSince = $this->createFilter('cheapestPrice.percentage', 'range', null, ['lte' => 90], $ratioSince->modify('-1 day'), $ratioSince->modify('+1 day'));

        (new Migration1791622183ConvertProductStreamPercentageFiltersToRatio())->update($this->connection);

        static::assertEquals(['lt' => 100], $this->fetchFilter($createdSince)['parameters']);
        static::assertEquals(['lte' => 90], $this->fetchFilter($editedSince)['parameters']);
        static::assertSame([], (new IndexerQueuer($this->connection))->getIndexers());
    }

    public function testLeavesAShopThatAlreadyWritesRatioConditionsUntouched(): void
    {
        // installed with 6.7.10.0 or later, or every condition was written since the update to it
        $this->resetScheduledIndexers();
        $since = $this->getRatioSince()->modify('+1 second');
        $this->createFilter('cheapestPrice.percentage', 'range', null, ['lt' => 100], $since);
        $this->createFilter('cheapestPrice.percentage', 'range', null, ['lte' => 90], $since);
        $this->createFilter('cheapestPrice.percentage', 'range', null, ['gte' => 50, 'lte' => 90], $since);
        $this->createFilter('cheapestPrice.percentage', 'equals', '75', null, $since);
        $this->createFilter('cheapestPrice.percentage', 'equalsAny', '90|75', null, $since);
        $this->createFilter('product.cheapestPrice.percentage', 'range', null, ['gt' => 0], $since);
        $this->createFilter('stock', 'range', null, ['gte' => 1], $since);
        $before = $this->fetchAllFilters();

        (new Migration1791622183ConvertProductStreamPercentageFiltersToRatio())->update($this->connection);

        static::assertSame($before, $this->fetchAllFilters());
        static::assertSame([], (new IndexerQueuer($this->connection))->getIndexers());
    }

    public function testRunningAgainLeavesConditionsWrittenAfterTheFirstRunUntouched(): void
    {
        $migration = new Migration1791622183ConvertProductStreamPercentageFiltersToRatio();
        $converted = $this->createFilter('cheapestPrice.percentage', 'range', null, ['gte' => 10], $this->getRatioSince()->modify('-1 day'));
        $migration->update($this->connection);

        $this->createFilter('cheapestPrice.percentage', 'range', null, ['lt' => 100], new \DateTimeImmutable());
        $this->createFilter('cheapestPrice.percentage', 'equals', '75', null, new \DateTimeImmutable());
        $before = $this->fetchAllFilters();

        $migration->update($this->connection);

        static::assertSame($before, $this->fetchAllFilters());
        static::assertEquals(['lte' => 90], $this->fetchFilter($converted)['parameters']);
    }

    /**
     * @param array<string, float|int|string|null>|string|null $parameters
     */
    #[DataProvider('unconvertibleFilterProvider')]
    public function testKeepsOtherFieldsAndConditionsItCannotConvert(string $field, string $type, ?string $value, array|string|null $parameters): void
    {
        $this->resetScheduledIndexers();
        $filterId = $this->createFilter($field, $type, $value, $parameters, $this->getRatioSince()->modify('-1 day'));
        $before = $this->fetchRawFilter($filterId);

        (new Migration1791622183ConvertProductStreamPercentageFiltersToRatio())->update($this->connection);

        static::assertSame($before, $this->fetchRawFilter($filterId));
        static::assertSame([], (new IndexerQueuer($this->connection))->getIndexers());
    }

    /**
     * @return iterable<string, array{string, string, string|null, array<string, float|int|string|null>|string|null}>
     */
    public static function unconvertibleFilterProvider(): iterable
    {
        yield 'other field' => ['cheapestPrice', 'range', null, ['gte' => 10]];
        yield 'is empty' => ['cheapestPrice.percentage', 'equals', null, null];
        yield 'unsupported type' => ['cheapestPrice.percentage', 'contains', '10', null];
        yield 'unknown range operator' => ['cheapestPrice.percentage', 'range', null, ['gte' => 10, 'foo' => 1]];
        yield 'non-numeric bound' => ['cheapestPrice.percentage', 'range', null, ['gte' => 'abc']];
        yield 'range without parameters' => ['cheapestPrice.percentage', 'range', null, null];
        yield 'range without bounds' => ['cheapestPrice.percentage', 'range', null, []];
        yield 'range with only empty bounds' => ['cheapestPrice.percentage', 'range', null, ['gte' => null, 'lte' => null]];
        yield 'range json without bounds object' => ['cheapestPrice.percentage', 'range', null, '"10"'];
        yield 'non-numeric value' => ['cheapestPrice.percentage', 'equalsAny', '10|abc', null];
    }

    public function testConvertsConditionsNextToOnesItCannotConvert(): void
    {
        $before = $this->getRatioSince()->modify('-1 day');
        $this->createFilter('cheapestPrice.percentage', 'contains', '10', null, $before, filterId: Uuid::fromHexToBytes(str_repeat('0', 32)));
        $convertible = $this->createFilter('cheapestPrice.percentage', 'range', null, ['gte' => 10], $before, filterId: Uuid::fromHexToBytes(str_repeat('f', 32)));

        (new Migration1791622183ConvertProductStreamPercentageFiltersToRatio())->update($this->connection);

        static::assertEquals(['lte' => 90], $this->fetchFilter($convertible)['parameters']);
    }

    public function testKeepsConditionsWhenTheRatioReleaseMigrationDidNotRun(): void
    {
        $filterId = $this->createFilter('cheapestPrice.percentage', 'range', null, ['gte' => 10], $this->getRatioSince()->modify('-1 day'));
        $this->connection->delete('migration', ['class' => Migration1770740885AddRevisionToConsentState::class]);

        (new Migration1791622183ConvertProductStreamPercentageFiltersToRatio())->update($this->connection);

        static::assertEquals(['gte' => 10], $this->fetchFilter($filterId)['parameters']);
    }

    /**
     * @param array<string, float|int|null>|null $parameters
     * @param array<string, mixed> $compiledBefore
     * @param array<string, mixed> $compiledAfter
     */
    #[DataProvider('compiledConditionProvider')]
    public function testConvertsTheCompiledFilterOfTheGroupWithTheConditions(
        string $field,
        string $type,
        ?string $value,
        ?array $parameters,
        array $compiledBefore,
        array $compiledAfter,
    ): void {
        $this->resetScheduledIndexers();
        $stock = ['type' => 'range', 'field' => 'product.stock', 'parameters' => ['gte' => 10]];
        $streamId = $this->createGroup(self::compiled($stock, $compiledBefore));
        $this->createFilter($field, $type, $value, $parameters, $this->getRatioSince()->modify('-1 day'), streamId: $streamId);
        $otherStreamId = $this->createGroup(self::compiled($stock));

        (new Migration1791622183ConvertProductStreamPercentageFiltersToRatio())->update($this->connection);

        static::assertEquals(self::compiled($stock, $compiledAfter), $this->fetchCompiledFilter($streamId));
        static::assertEquals(self::compiled($stock), $this->fetchCompiledFilter($otherStreamId));
        // the products can be matched right away, there is nothing to compile first
        static::assertSame(
            ['product.indexer' => [ProductIndexer::STREAM_UPDATER, ProductIndexer::MANY_TO_MANY_ID_FIELD_UPDATER]],
            (new IndexerQueuer($this->connection))->getIndexers()
        );
    }

    /**
     * @return iterable<string, array{string, string, string|null, array<string, float|int|null>|null, array<string, mixed>, array<string, mixed>}>
     */
    public static function compiledConditionProvider(): iterable
    {
        $field = 'product.cheapestPrice.percentage';

        yield 'at least 10 % off' => ['cheapestPrice.percentage', 'range', null, ['gte' => 10], ['type' => 'range', 'field' => $field, 'parameters' => ['gte' => 10]], ['type' => 'range', 'field' => $field, 'parameters' => ['lte' => 90]]];
        yield 'range with an empty bound' => ['cheapestPrice.percentage', 'range', null, ['gte' => 10, 'lte' => null], ['type' => 'range', 'field' => $field, 'parameters' => ['gte' => 10, 'lte' => null]], ['type' => 'range', 'field' => $field, 'parameters' => ['lte' => 90, 'gte' => null]]];
        yield 'exactly 25 % off' => ['cheapestPrice.percentage', 'equals', '25', null, ['type' => 'equals', 'field' => $field, 'value' => '25'], ['type' => 'equals', 'field' => $field, 'value' => '75']];
        yield 'any of 10 or 25 % off' => ['cheapestPrice.percentage', 'equalsAny', '10|25', null, ['type' => 'equalsAny', 'field' => $field, 'value' => '10|25'], ['type' => 'equalsAny', 'field' => $field, 'value' => '90|75']];
        // compiling drops the 0 of any-of values
        yield 'any of 0 or 25 % off' => ['cheapestPrice.percentage', 'equalsAny', '0|25', null, ['type' => 'equalsAny', 'field' => $field, 'value' => '25'], ['type' => 'equalsAny', 'field' => $field, 'value' => '100|75']];
        yield 'not exactly 25 % off' => [
            'cheapestPrice.percentage',
            'equals',
            '25',
            null,
            ['type' => 'not', 'operator' => 'AND', 'queries' => [['type' => 'equals', 'field' => $field, 'value' => '25']]],
            ['type' => 'not', 'operator' => 'AND', 'queries' => [['type' => 'equals', 'field' => $field, 'value' => '75']]],
        ];
        yield 'entity prefixed field' => [$field, 'range', null, ['gt' => 1], ['type' => 'range', 'field' => $field, 'parameters' => ['gt' => 1]], ['type' => 'range', 'field' => $field, 'parameters' => ['lt' => 99]]];
    }

    public function testConvertsEveryCompiledConditionOfTheGroup(): void
    {
        $this->resetScheduledIndexers();
        $before = $this->getRatioSince()->modify('-1 day');
        $field = 'product.cheapestPrice.percentage';
        $streamId = $this->createGroup([[
            'type' => 'multi',
            'operator' => 'OR',
            'queries' => [
                ['type' => 'multi', 'operator' => 'AND', 'queries' => [
                    ['type' => 'range', 'field' => $field, 'parameters' => ['gte' => 10]],
                    ['type' => 'not', 'operator' => 'AND', 'queries' => [['type' => 'equals', 'field' => $field, 'value' => '25']]],
                ]],
                ['type' => 'multi', 'operator' => 'AND', 'queries' => [
                    ['type' => 'range', 'field' => $field, 'parameters' => ['gte' => 10]],
                ]],
            ],
        ]]);
        $this->createFilter('cheapestPrice.percentage', 'range', null, ['gte' => 10], $before, filterId: Uuid::fromHexToBytes(str_repeat('0', 32)), streamId: $streamId);
        $this->createFilter('cheapestPrice.percentage', 'range', null, ['gte' => 10], $before, filterId: Uuid::fromHexToBytes(str_repeat('5', 32)), streamId: $streamId);
        $this->createFilter('cheapestPrice.percentage', 'equals', '25', null, $before, filterId: Uuid::fromHexToBytes(str_repeat('f', 32)), streamId: $streamId);

        (new Migration1791622183ConvertProductStreamPercentageFiltersToRatio())->update($this->connection);

        static::assertEquals([[
            'type' => 'multi',
            'operator' => 'OR',
            'queries' => [
                ['type' => 'multi', 'operator' => 'AND', 'queries' => [
                    ['type' => 'range', 'field' => $field, 'parameters' => ['lte' => 90]],
                    ['type' => 'not', 'operator' => 'AND', 'queries' => [['type' => 'equals', 'field' => $field, 'value' => '75']]],
                ]],
                ['type' => 'multi', 'operator' => 'AND', 'queries' => [
                    ['type' => 'range', 'field' => $field, 'parameters' => ['lte' => 90]],
                ]],
            ],
        ]], $this->fetchCompiledFilter($streamId));
        static::assertSame(['product.indexer'], array_keys((new IndexerQueuer($this->connection))->getIndexers()));
    }

    public function testKeepsTheCompiledConditionsItDoesNotConvert(): void
    {
        $this->resetScheduledIndexers();
        $ratioSince = $this->getRatioSince();
        $field = 'product.cheapestPrice.percentage';
        $writtenSince = ['type' => 'range', 'field' => $field, 'parameters' => ['lte' => 95]];
        $unsupportedType = ['type' => 'contains', 'field' => $field, 'value' => '25'];
        $streamId = $this->createGroup(self::compiled(
            $writtenSince,
            $unsupportedType,
            ['type' => 'range', 'field' => $field, 'parameters' => ['gte' => 10]],
            ['type' => 'equals', 'field' => $field, 'value' => '25'],
        ));
        $this->createFilter('cheapestPrice.percentage', 'range', null, ['lte' => 95], $ratioSince->modify('+1 day'), streamId: $streamId);
        $this->createFilter('cheapestPrice.percentage', 'contains', '25', null, $ratioSince->modify('-1 day'), streamId: $streamId);
        $this->createFilter('cheapestPrice.percentage', 'range', null, ['gte' => 10], $ratioSince->modify('-1 day'), streamId: $streamId);
        $this->createFilter('cheapestPrice.percentage', 'equals', '25', null, $ratioSince->modify('-1 day'), streamId: $streamId);

        (new Migration1791622183ConvertProductStreamPercentageFiltersToRatio())->update($this->connection);

        static::assertEquals(self::compiled(
            $writtenSince,
            $unsupportedType,
            ['type' => 'range', 'field' => $field, 'parameters' => ['lte' => 90]],
            ['type' => 'equals', 'field' => $field, 'value' => '75'],
        ), $this->fetchCompiledFilter($streamId));
        static::assertSame(['product.indexer'], array_keys((new IndexerQueuer($this->connection))->getIndexers()));
    }

    /**
     * @param list<array<string, mixed>>|null $compiled
     */
    #[DataProvider('unmatchedCompiledFilterProvider')]
    public function testCompilesTheGroupAgainWhenItsCompiledFilterDoesNotMatchUpWithTheConditions(?array $compiled, bool $invalid = false, bool $sameConditionWrittenSince = false): void
    {
        $this->resetScheduledIndexers();
        $ratioSince = $this->getRatioSince();
        $streamId = $this->createGroup($compiled, $invalid);
        $converted = $this->createFilter('cheapestPrice.percentage', 'range', null, ['gte' => 10], $ratioSince->modify('-1 day'), streamId: $streamId);
        if ($sameConditionWrittenSince) {
            $this->createFilter('cheapestPrice.percentage', 'range', null, ['gte' => 10], $ratioSince->modify('+1 day'), streamId: $streamId);
        }

        (new Migration1791622183ConvertProductStreamPercentageFiltersToRatio())->update($this->connection);

        static::assertEquals(['lte' => 90], $this->fetchFilter($converted)['parameters']);
        static::assertEquals($compiled, $this->fetchCompiledFilter($streamId));
        static::assertSame([
            'product_stream.indexer' => [],
            'product.indexer' => [ProductIndexer::STREAM_UPDATER, ProductIndexer::MANY_TO_MANY_ID_FIELD_UPDATER],
        ], (new IndexerQueuer($this->connection))->getIndexers());
    }

    /**
     * @return iterable<string, array{0: list<array<string, mixed>>|null, 1?: bool, 2?: bool}>
     */
    public static function unmatchedCompiledFilterProvider(): iterable
    {
        $atLeast10Off = ['type' => 'range', 'field' => 'product.cheapestPrice.percentage', 'parameters' => ['gte' => 10]];

        yield 'not compiled' => [null];
        yield 'compiled without the condition' => [self::compiled(['type' => 'range', 'field' => 'product.stock', 'parameters' => ['gte' => 10]])];
        yield 'compiled with the condition twice' => [self::compiled($atLeast10Off, $atLeast10Off)];
        yield 'invalid' => [self::compiled($atLeast10Off), true];
        yield 'the same condition written since the ratio release' => [self::compiled($atLeast10Off, $atLeast10Off), false, true];
    }

    private function getRatioSince(): \DateTimeImmutable
    {
        $ratioSince = $this->connection->fetchOne(
            'SELECT `update` FROM `migration` WHERE `class` = :class',
            ['class' => Migration1770740885AddRevisionToConsentState::class]
        );
        static::assertIsString($ratioSince);

        return new \DateTimeImmutable($ratioSince);
    }

    private function resetScheduledIndexers(): void
    {
        $this->connection->delete('system_config', ['configuration_key' => IndexerQueuer::INDEXER_KEY]);
    }

    /**
     * The compiled filter of a group as the administration writes it, an OR container holding an AND container with the conditions
     *
     * @param array<string, mixed> ...$conditions
     *
     * @return list<array<string, mixed>>
     */
    private static function compiled(array ...$conditions): array
    {
        return [['type' => 'multi', 'operator' => 'OR', 'queries' => [['type' => 'multi', 'operator' => 'AND', 'queries' => array_values($conditions)]]]];
    }

    /**
     * @param list<array<string, mixed>>|null $compiled
     */
    private function createGroup(?array $compiled, bool $invalid = false): string
    {
        $streamId = Uuid::randomBytes();
        $this->connection->insert('product_stream', [
            'id' => $streamId,
            'api_filter' => $compiled === null ? null : json_encode($compiled, \JSON_THROW_ON_ERROR),
            'invalid' => (int) $invalid,
            'created_at' => $this->getRatioSince()->modify('-1 day')->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $streamId;
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function fetchCompiledFilter(string $streamId): ?array
    {
        $apiFilter = $this->connection->fetchOne('SELECT `api_filter` FROM `product_stream` WHERE `id` = :id', ['id' => $streamId]);

        return \is_string($apiFilter) ? json_decode($apiFilter, true, 512, \JSON_THROW_ON_ERROR) : null;
    }

    /**
     * @param array<string, float|int|string|null>|string|null $parameters raw JSON when given as string
     */
    private function createFilter(
        string $field,
        string $type,
        ?string $value,
        array|string|null $parameters,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $updatedAt = null,
        ?string $filterId = null,
        ?string $streamId = null,
    ): string {
        if ($streamId === null) {
            $streamId = Uuid::randomBytes();
            $this->connection->insert('product_stream', [
                'id' => $streamId,
                'invalid' => 0,
                'created_at' => $createdAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        $filterId ??= Uuid::randomBytes();
        $this->connection->insert('product_stream_filter', [
            'id' => $filterId,
            'product_stream_id' => $streamId,
            'type' => $type,
            'field' => $field,
            'value' => $value,
            'parameters' => \is_array($parameters) ? json_encode($parameters, \JSON_THROW_ON_ERROR) : $parameters,
            'position' => 0,
            'created_at' => $createdAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'updated_at' => $updatedAt?->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $filterId;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchRawFilter(string $filterId): array
    {
        $filter = $this->connection->fetchAssociative(
            'SELECT `type`, `field`, `value`, `parameters`, `updated_at` FROM `product_stream_filter` WHERE `id` = :id',
            ['id' => $filterId]
        );
        static::assertIsArray($filter);

        return $filter;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchAllFilters(): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT `id`, `product_stream_id`, `parent_id`, `type`, `field`, `operator`, `value`, `parameters`, `position`, `created_at`, `updated_at`
             FROM `product_stream_filter`
             ORDER BY `id`'
        );
    }

    /**
     * @return array{value: string|null, parameters: array<string, mixed>|null, updated_at: string|null}
     */
    private function fetchFilter(string $filterId): array
    {
        $filter = $this->connection->fetchAssociative(
            'SELECT `value`, `parameters`, `updated_at` FROM `product_stream_filter` WHERE `id` = :id',
            ['id' => $filterId]
        );
        static::assertIsArray($filter);

        return [
            'value' => $filter['value'],
            'parameters' => $filter['parameters'] === null ? null : json_decode((string) $filter['parameters'], true, 512, \JSON_THROW_ON_ERROR),
            'updated_at' => $filter['updated_at'],
        ];
    }
}
