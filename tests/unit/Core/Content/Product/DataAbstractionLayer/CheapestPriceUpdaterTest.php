<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Content\Product\DataAbstractionLayer\AbstractCheapestPriceQuantitySelector;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceContainer;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPriceUpdater;
use Shopware\Core\Content\Product\Events\ProductIndexerEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Assert\Serialization;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CheapestPriceUpdater::class)]
class CheapestPriceUpdaterTest extends TestCase
{
    private Connection&Stub $connection;

    private QueryBuilder&Stub $queryBuilder;

    private AbstractCheapestPriceQuantitySelector&Stub $quantitySelector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->queryBuilder = static::createStub(QueryBuilder::class);
        $this->queryBuilder->method('setParameter')->willReturnSelf();
        $this->queryBuilder->method('select')->willReturnSelf();
        $this->queryBuilder->method('from')->willReturnSelf();
        $this->queryBuilder->method('innerJoin')->willReturnSelf();
        $this->queryBuilder->method('leftJoin')->willReturnSelf();
        $this->queryBuilder->method('andWhere')->willReturnSelf();

        $this->quantitySelector = static::createStub(AbstractCheapestPriceQuantitySelector::class);
        $this->quantitySelector->method('add')->willReturnSelf();

        $this->connection = static::createStub(Connection::class);
        $this->connection->method('createQueryBuilder')->willReturn($this->queryBuilder);
    }

    public function testDispatchesProductIndexerEvent(): void
    {
        $parentId = Uuid::randomHex();
        $variantId = Uuid::randomHex();

        $mockedData = [
            $this->createPriceRow($parentId, $variantId),
            $this->createPriceRow(
                $parentId,
                $parentId,
                null,
                '{"cb7d2554b0ce847cd82f3ac9bd1c0dfca":{"net":16.806722689076,"gross":20,"linked":true,"listPrice":{"net":84.033613445378,"gross":100,"linked":true,"listPrice":null,"currencyId":"b7d2554b0ce847cd82f3ac9bd1c0dfca","extensions":[],"percentage":null,"regulationPrice":null},"currencyId":"b7d2554b0ce847cd82f3ac9bd1c0dfca","percentage":{"net":80,"gross":80},"regulationPrice":null}}'
            ),
        ];

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $updater = $this->createMockedUpdater($mockedData, [], $dispatcher);

        $parentIds = [Uuid::randomHex()];
        $context = Context::createDefaultContext();

        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::callback(static function (ProductIndexerEvent $event) use ($context, $variantId) {
                return $event->getIds() === [$variantId] && $event->getContext() === $context;
            }));

        $updater->update($parentIds, $context);
    }

    /**
     * The fetched sales channel visibility only surfaces as the container serialized into the
     * `cheapest_price` UPDATE, so the test captures that written value (see "Asserting writes
     * when there is no other seam" in coding-guidelines/core/unit-tests.md).
     *
     * @param list<string> $salesChannelIds
     */
    #[TestDox('The written cheapest_price container carries the variant sales channel ids')]
    #[DataProvider('salesChannelVisibilityProvider')]
    public function testUpdateWritesSalesChannelIdsIntoCheapestPriceContainer(array $salesChannelIds): void
    {
        $parentId = Uuid::randomHex();
        $variantId = Uuid::randomHex();

        $mockedData = [
            // currencyId is required: the full update() run builds the price accessor from it
            $this->createPriceRow($parentId, $variantId, 'default', '{"cb7d2554b0ce847cd82f3ac9bd1c0dfca":{"net":16.806722689076,"gross":20,"linked":true,"currencyId":"b7d2554b0ce847cd82f3ac9bd1c0dfca"}}'),
        ];

        $mockedVisibility = array_map(
            static fn (string $salesChannelId): array => ['product_id' => $variantId, 'sales_channel_id' => $salesChannelId],
            $salesChannelIds
        );

        $updater = $this->createMockedUpdater($mockedData, $mockedVisibility);

        $writtenPrices = [];
        $cheapestPriceStatement = $this->createMock(Statement::class);
        $cheapestPriceStatement->method('bindValue')->willReturnCallback(
            static function (string $param, mixed $value) use (&$writtenPrices): void {
                if ($param === 'price') {
                    $writtenPrices[] = (string) $value;
                }
            }
        );
        $cheapestPriceStatement->expects($this->once())->method('executeStatement')->willReturn(1);

        $accessorStatement = static::createStub(Statement::class);
        $this->connection->method('prepare')->willReturnCallback(
            static fn (string $sql): Statement => str_contains($sql, 'cheapest_price_accessor') ? $accessorStatement : $cheapestPriceStatement
        );

        $updater->update([$parentId], Context::createDefaultContext());

        static::assertCount(1, $writtenPrices);
        $container = Serialization::assertUnserializedInstanceOf(CheapestPriceContainer::class, $writtenPrices[0]);

        $rulePrices = $container->getPricesForVariant($variantId);
        static::assertArrayHasKey('default', $rulePrices);
        static::assertSame($salesChannelIds, $rulePrices['default']['sales_channel_ids']);
    }

    public static function salesChannelVisibilityProvider(): \Generator
    {
        yield 'variant visible in two sales channels' => [[Uuid::randomHex(), Uuid::randomHex()]];
        yield 'variant without visibility rows' => [[]];
    }

    /**
     * @param array<int, array<string, mixed>> $dataResults
     * @param array<int, array<string, mixed>> $visibilityResults
     */
    private function createMockedUpdater(array $dataResults, array $visibilityResults, ?EventDispatcherInterface $dispatcher = null): CheapestPriceUpdater
    {
        $result1 = static::createStub(Result::class);
        $result1->method('fetchAllAssociative')->willReturn($dataResults);

        $result2 = static::createStub(Result::class);
        $result2->method('fetchAllAssociative')->willReturn([]);

        $this->queryBuilder->method('executeQuery')->willReturnOnConsecutiveCalls($result1, $result2);

        $this->connection->method('fetchAllAssociative')
            ->willReturnCallback(
                // the accessor pre-load query has its own row shape; only the visibility query gets rows
                static fn (string $sql): array => str_contains($sql, 'cheapest_price_accessor') ? [] : $visibilityResults
            );

        return new CheapestPriceUpdater(
            $this->connection,
            $this->quantitySelector,
            $dispatcher ?? static::createStub(EventDispatcherInterface::class)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function createPriceRow(string $parentId, string $variantId, ?string $ruleId = null, ?string $priceJson = null): array
    {
        return [
            'parent_id' => $parentId,
            'variant_id' => $variantId,
            'rule_id' => $ruleId,
            'is_ranged' => 0,
            'price' => $priceJson ?? '{}',
            'min_purchase' => 1,
            'unit_id' => null,
            'purchase_unit' => null,
            'reference_unit' => null,
            'child_count' => null,
        ];
    }
}
