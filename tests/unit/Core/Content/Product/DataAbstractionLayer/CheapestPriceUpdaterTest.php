<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
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
    public function testDispatchesProductIndexerEvent(): void
    {
        $parentId = Uuid::randomHex();
        $variantId = Uuid::randomHex();

        $mockedData = [
            [
                'parent_id' => $parentId,
                'variant_id' => $variantId,
                'rule_id' => null,
                'is_ranged' => 0,
                'price' => '{}',
                'min_purchase' => 1,
                'unit_id' => null,
                'purchase_unit' => null,
                'reference_unit' => null,
                'child_count' => null,
            ],
            [
                'parent_id' => $parentId,
                'variant_id' => $parentId,
                'rule_id' => null,
                'is_ranged' => 0,
                'price' => '{"cb7d2554b0ce847cd82f3ac9bd1c0dfca": {"net": 16.806722689076, "gross": 20.0, "linked": true, "listPrice": {"net": 84.033613445378, "gross": 100, "linked": true, "listPrice": null, "currencyId": "b7d2554b0ce847cd82f3ac9bd1c0dfca", "extensions": [], "percentage": null, "regulationPrice": null}, "currencyId": "b7d2554b0ce847cd82f3ac9bd1c0dfca", "percentage": {"net": 80.0, "gross": 80.0}, "regulationPrice": null}}',
                'min_purchase' => 1,
                'unit_id' => null,
                'purchase_unit' => null,
                'reference_unit' => null,
                'child_count' => null,
            ],
        ];

        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn($mockedData);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('executeQuery')->willReturn($result);

        $connection = $this->createMock(Connection::class);
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);

        $quantitySelector = $this->createMock(AbstractCheapestPriceQuantitySelector::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $updater = new CheapestPriceUpdater($connection, $quantitySelector, $dispatcher);

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
            [
                'parent_id' => $parentId,
                'variant_id' => $variantId,
                'rule_id' => 'default',
                'is_ranged' => 0,
                'price' => '{"cb7d2554b0ce847cd82f3ac9bd1c0dfca": {"net": 16.806722689076, "gross": 20.0, "linked": true}}',
                'min_purchase' => 1,
                'unit_id' => null,
                'purchase_unit' => null,
                'reference_unit' => null,
                'child_count' => null,
                'sales_channel_ids' => $salesChannelId1 . ',' . $salesChannelId2,
            ],
        ];

        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn($mockedData);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('executeQuery')->willReturn($result);

        $connection = $this->createMock(Connection::class);
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);

        $updater = new CheapestPriceUpdater(
            $connection,
            $this->createMock(AbstractCheapestPriceQuantitySelector::class),
            $this->createMock(EventDispatcherInterface::class)
        );

        $reflection = new \ReflectionClass($updater);
        $method = $reflection->getMethod('fetchPrices');
        $method->setAccessible(true);

        $prices = $method->invoke($updater, [$parentId], Context::createDefaultContext());

        static::assertArrayHasKey($parentId, $prices);
        static::assertArrayHasKey($variantId, $prices[$parentId]);
        static::assertArrayHasKey('default', $prices[$parentId][$variantId]);

        $salesChannelIds = $prices[$parentId][$variantId]['default']['sales_channel_ids'];
        static::assertEquals([$salesChannelId1, $salesChannelId2], $salesChannelIds);
    }

    public function testFetchPricesWithEmptySalesChannelData(): void
    {
        $parentId = Uuid::randomHex();
        $variantId = Uuid::randomHex();

        $mockedData = [
            [
                'parent_id' => $parentId,
                'variant_id' => $variantId,
                'rule_id' => 'default',
                'is_ranged' => 0,
                'price' => '{"cb7d2554b0ce847cd82f3ac9bd1c0dfca": {"net": 16.806722689076, "gross": 20.0, "linked": true}}',
                'min_purchase' => 1,
                'unit_id' => null,
                'purchase_unit' => null,
                'reference_unit' => null,
                'child_count' => null,
                'sales_channel_ids' => '',
            ],
        ];

        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn($mockedData);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('executeQuery')->willReturn($result);

        $connection = $this->createMock(Connection::class);
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);

        $quantitySelector = $this->createMock(AbstractCheapestPriceQuantitySelector::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $updater = new CheapestPriceUpdater($connection, $quantitySelector, $dispatcher);

        $reflection = new \ReflectionClass($updater);
        $method = $reflection->getMethod('fetchPrices');
        $method->setAccessible(true);

        $prices = $method->invoke($updater, [$parentId], Context::createDefaultContext());

        static::assertArrayHasKey($parentId, $prices);
        static::assertArrayHasKey($variantId, $prices[$parentId]);
        static::assertArrayHasKey('default', $prices[$parentId][$variantId]);

        $salesChannelIds = $prices[$parentId][$variantId]['default']['sales_channel_ids'];
        static::assertEquals([], $salesChannelIds);
    }
}
