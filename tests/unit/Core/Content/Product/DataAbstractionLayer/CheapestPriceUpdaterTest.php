<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Content\Product\DataAbstractionLayer\AbstractCheapestPriceQuantitySelector;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPriceUpdater;
use Shopware\Core\Content\Product\Events\ProductIndexerEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(CheapestPriceUpdater::class)]
class CheapestPriceUpdaterTest extends TestCase
{
    private Connection&MockObject $connection;

    private QueryBuilder&MockObject $queryBuilder;

    private AbstractCheapestPriceQuantitySelector&MockObject $quantitySelector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->queryBuilder = $this->createMock(QueryBuilder::class);
        $this->queryBuilder->method('setParameter')->willReturnSelf();
        $this->queryBuilder->method('select')->willReturnSelf();
        $this->queryBuilder->method('from')->willReturnSelf();
        $this->queryBuilder->method('innerJoin')->willReturnSelf();
        $this->queryBuilder->method('leftJoin')->willReturnSelf();
        $this->queryBuilder->method('andWhere')->willReturnSelf();

        $this->quantitySelector = $this->createMock(AbstractCheapestPriceQuantitySelector::class);
        $this->quantitySelector->method('add')->willReturnSelf();

        $this->connection = $this->createMock(Connection::class);
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
        $updater = $this->createMockedUpdater($mockedData, [], [], $dispatcher);

        $parentIds = [Uuid::randomHex()];
        $context = Context::createDefaultContext();

        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::callback(function (ProductIndexerEvent $event) use ($context, $variantId) {
                return $event->getIds() === [$variantId] && $event->getContext() === $context;
            }));

        $updater->update($parentIds, $context);
    }

    public function testFetchPricesWithSalesChannelData(): void
    {
        $parentId = Uuid::randomHex();
        $variantId = Uuid::randomHex();
        $salesChannelId1 = Uuid::randomHex();
        $salesChannelId2 = Uuid::randomHex();

        $mockedData = [
            $this->createPriceRow($parentId, $variantId, 'default', '{"cb7d2554b0ce847cd82f3ac9bd1c0dfca":{"net":16.806722689076,"gross":20,"linked":true}}'),
        ];

        $mockedVisibility = [
            [
                'product_id' => $variantId,
                'sales_channel_id' => $salesChannelId1,
            ],
            [
                'product_id' => $variantId,
                'sales_channel_id' => $salesChannelId2,
            ],
        ];

        $updater = $this->createMockedUpdater($mockedData, [], $mockedVisibility);
        $prices = $this->invokeFetchPrices($updater, [$parentId], Context::createDefaultContext());

        $this->assertSalesChannelIds($prices, $parentId, $variantId, [$salesChannelId1, $salesChannelId2]);
    }

    public function testFetchPricesWithEmptySalesChannelData(): void
    {
        $parentId = Uuid::randomHex();
        $variantId = Uuid::randomHex();

        $mockedData = [
            $this->createPriceRow($parentId, $variantId, 'default', '{"cb7d2554b0ce847cd82f3ac9bd1c0dfca":{"net":16.806722689076,"gross":20,"linked":true}}'),
        ];

        $updater = $this->createMockedUpdater($mockedData, [], []);
        $prices = $this->invokeFetchPrices($updater, [$parentId], Context::createDefaultContext());

        $this->assertSalesChannelIds($prices, $parentId, $variantId, []);
    }

    /**
     * @param array<int, array<string, mixed>> $dataResults
     * @param array<int, array<string, mixed>> $defaultsResults
     * @param array<int, array<string, mixed>> $visibilityResults
     */
    private function createMockedUpdater(array $dataResults, array $defaultsResults, array $visibilityResults, ?EventDispatcherInterface $dispatcher = null): CheapestPriceUpdater
    {
        $result1 = $this->createMock(Result::class);
        $result1->method('fetchAllAssociative')->willReturn($dataResults);

        $result2 = $this->createMock(Result::class);
        $result2->method('fetchAllAssociative')->willReturn($defaultsResults);

        $this->queryBuilder->method('executeQuery')->willReturnOnConsecutiveCalls($result1, $result2);

        $this->connection->method('fetchAllAssociative')
            ->willReturn($visibilityResults);

        return new CheapestPriceUpdater(
            $this->connection,
            $this->quantitySelector,
            $dispatcher ?? $this->createMock(EventDispatcherInterface::class)
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

    /**
     * @param array<string> $parentIds
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function invokeFetchPrices(CheapestPriceUpdater $updater, array $parentIds, Context $context): array
    {
        $reflection = new \ReflectionClass($updater);
        $method = $reflection->getMethod('fetchPrices');
        $method->setAccessible(true);

        return $method->invoke($updater, $parentIds, $context);
    }

    /**
     * @param array<string, array<string, array<string, mixed>>> $prices
     * @param array<string> $expectedSalesChannelIds
     */
    private function assertSalesChannelIds(array $prices, string $parentId, string $variantId, array $expectedSalesChannelIds): void
    {
        static::assertArrayHasKey($parentId, $prices);
        static::assertArrayHasKey($variantId, $prices[$parentId], 'Variant should exist in prices');

        $variantPrices = $prices[$parentId][$variantId];
        foreach ($variantPrices as $ruleData) {
            if (\is_array($ruleData) && isset($ruleData['sales_channel_ids'])) {
                static::assertEquals($expectedSalesChannelIds, $ruleData['sales_channel_ids']);

                return;
            }
        }

        static::fail('Should have variant entry with sales_channel_ids');
    }
}
