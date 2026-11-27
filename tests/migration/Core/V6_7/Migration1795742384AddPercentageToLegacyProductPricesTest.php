<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\IndexerQueuer;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1795742384AddPercentageToLegacyProductPrices;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1795742384AddPercentageToLegacyProductPrices::class)]
class Migration1795742384AddPercentageToLegacyProductPricesTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->ids = new IdsCollection();
    }

    public function testUpdateAddsThePercentageToEveryCurrencyOfAProductPrice(): void
    {
        $otherCurrency = Uuid::randomHex();
        $this->insertProduct('product', [
            ...self::legacyPrice(gross: 75, net: 63.03, listGross: 100, listNet: 84.03),
            ...self::legacyPrice(gross: 30, net: 25.21, listGross: 50, listNet: 42.01, currencyId: $otherCurrency),
        ]);

        (new Migration1795742384AddPercentageToLegacyProductPrices())->update($this->connection);

        static::assertEquals(['net' => 24.99, 'gross' => 25.0], $this->fetchPercentage('product', 'product'));
        static::assertEquals(['net' => 39.99, 'gross' => 40.0], $this->fetchPercentage('product', 'product', currencyId: $otherCurrency));
    }

    public function testUpdateKeepsThePercentageOfTheOtherCurrencies(): void
    {
        $otherCurrency = Uuid::randomHex();
        $price = [
            ...self::legacyPrice(gross: 75, net: 63.03, listGross: 100, listNet: 84.03),
            ...self::legacyPrice(gross: 30, net: 25.21, listGross: 50, listNet: 42.01, currencyId: $otherCurrency),
        ];
        $price['c' . $otherCurrency]['percentage'] = ['net' => 10.0, 'gross' => 10.0];
        $this->insertProduct('product', $price);

        (new Migration1795742384AddPercentageToLegacyProductPrices())->update($this->connection);

        static::assertEquals(['net' => 24.99, 'gross' => 25.0], $this->fetchPercentage('product', 'product'));
        static::assertEquals(['net' => 10.0, 'gross' => 10.0], $this->fetchPercentage('product', 'product', currencyId: $otherCurrency));
    }

    public function testUpdateAddsThePercentageToAdvancedPrices(): void
    {
        $this->insertProduct('product', self::legacyPrice(gross: 75, net: 63.03));
        $this->insertAdvancedPrice('advanced', product: 'product', price: self::legacyPrice(gross: 60, net: 50.42, listGross: 80, listNet: 67.23));

        (new Migration1795742384AddPercentageToLegacyProductPrices())->update($this->connection);

        static::assertEquals(
            ['net' => 25.0, 'gross' => 25.0],
            $this->fetchPercentage('product_price', 'advanced')
        );
    }

    public function testUpdateReplacesAPercentageStoredAsNull(): void
    {
        $price = self::legacyPrice(gross: 75, net: 63.03, listGross: 100, listNet: 84.03);
        $price['c' . Defaults::CURRENCY]['percentage'] = null;
        $this->insertProduct('product', $price);

        (new Migration1795742384AddPercentageToLegacyProductPrices())->update($this->connection);

        static::assertEquals(
            ['net' => 24.99, 'gross' => 25.0],
            $this->fetchPercentage('product', 'product')
        );
    }

    public function testUpdateSetsZeroForTheSideWithoutAListPrice(): void
    {
        $this->insertProduct('product', self::legacyPrice(gross: 75, net: 63.03, listGross: 100, listNet: 0));

        (new Migration1795742384AddPercentageToLegacyProductPrices())->update($this->connection);

        static::assertEquals(
            ['net' => 0.0, 'gross' => 25.0],
            $this->fetchPercentage('product', 'product')
        );
    }

    public function testUpdateKeepsPricesThatNeedNoPercentage(): void
    {
        $withPercentage = self::legacyPrice(gross: 75, net: 63.03, listGross: 100, listNet: 84.03);
        $withPercentage['c' . Defaults::CURRENCY]['percentage'] = ['net' => 10.0, 'gross' => 10.0];
        $withoutListPrice = self::legacyPrice(gross: 75, net: 63.03);
        $withZeroListPrice = self::legacyPrice(gross: 75, net: 63.03, listGross: 0, listNet: 0);
        $this->insertProduct('with-percentage', $withPercentage);
        $this->insertProduct('without-list-price', $withoutListPrice);
        $this->insertProduct('with-zero-list-price', $withZeroListPrice);
        $this->insertProduct('inheriting-variant', null);

        (new Migration1795742384AddPercentageToLegacyProductPrices())->update($this->connection);

        static::assertEquals($withPercentage, $this->fetchPrice('product', 'with-percentage'));
        static::assertEquals($withoutListPrice, $this->fetchPrice('product', 'without-list-price'));
        static::assertEquals($withZeroListPrice, $this->fetchPrice('product', 'with-zero-list-price'));
        static::assertNull($this->fetchPrice('product', 'inheriting-variant'));
    }

    public function testUpdateAddsThePercentageToEveryVersionOfAProduct(): void
    {
        $versionId = Uuid::randomHex();
        $this->insertProduct('product', self::legacyPrice(gross: 75, net: 63.03, listGross: 100, listNet: 84.03));
        $this->insertProduct('product', self::legacyPrice(gross: 75, net: 63.03, listGross: 100, listNet: 84.03), $versionId);

        (new Migration1795742384AddPercentageToLegacyProductPrices())->update($this->connection);

        static::assertEquals(['net' => 24.99, 'gross' => 25.0], $this->fetchPercentage('product', 'product'));
        static::assertEquals(['net' => 24.99, 'gross' => 25.0], $this->fetchPercentage('product', 'product', versionId: $versionId));
    }

    public function testUpdateGoesOnAfterABatchOfPricesItLeavesUnchanged(): void
    {
        // a whole batch of prices that match the query but need no percentage
        $queue = new MultiInsertQueryQueue($this->connection, 1000);
        for ($i = 0; $i < 1000; ++$i) {
            $queue->addInsert('product', $this->productRow('zero-list-price-' . $i, self::legacyPrice(gross: 75, net: 63.03, listGross: 0, listNet: 0)));
        }
        $queue->execute();
        // sorts after the whole batch
        $this->ids->set('legacy', 'ffffffffffffffffffffffffffffffff');
        $this->insertProduct('legacy', self::legacyPrice(gross: 75, net: 63.03, listGross: 100, listNet: 84.03));

        (new Migration1795742384AddPercentageToLegacyProductPrices())->update($this->connection);

        static::assertEquals(
            ['net' => 24.99, 'gross' => 25.0],
            $this->fetchPercentage('product', 'legacy')
        );
    }

    public function testUpdateReindexesTheProductsWhenItAddsAPercentage(): void
    {
        $this->clearIndexerQueue();
        $this->insertProduct('product', self::legacyPrice(gross: 75, net: 63.03, listGross: 100, listNet: 84.03));

        (new Migration1795742384AddPercentageToLegacyProductPrices())->update($this->connection);

        static::assertSame(
            ['product.indexer' => []],
            (new IndexerQueuer($this->connection))->getIndexers()
        );
    }

    public function testUpdateReindexesNothingWhenNoPriceNeedsAPercentage(): void
    {
        $this->insertProduct('legacy', self::legacyPrice(gross: 75, net: 63.03, listGross: 100, listNet: 84.03));
        $this->insertProduct('with-zero-list-price', self::legacyPrice(gross: 75, net: 63.03, listGross: 0, listNet: 0));

        (new Migration1795742384AddPercentageToLegacyProductPrices())->update($this->connection);
        $this->clearIndexerQueue();
        (new Migration1795742384AddPercentageToLegacyProductPrices())->update($this->connection);

        static::assertSame([], (new IndexerQueuer($this->connection))->getIndexers());
    }

    public function testUpdateTwice(): void
    {
        $this->insertProduct('product', self::legacyPrice(gross: 75, net: 63.03, listGross: 100, listNet: 84.03));

        $migration = new Migration1795742384AddPercentageToLegacyProductPrices();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertEquals(
            ['net' => 24.99, 'gross' => 25.0],
            $this->fetchPercentage('product', 'product')
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function legacyPrice(
        float $gross,
        float $net,
        ?float $listGross = null,
        ?float $listNet = null,
        string $currencyId = Defaults::CURRENCY,
    ): array {
        return ['c' . $currencyId => [
            'currencyId' => $currencyId,
            'gross' => $gross,
            'net' => $net,
            'linked' => true,
            'listPrice' => $listGross === null ? null : ['currencyId' => $currencyId, 'gross' => $listGross, 'net' => $listNet, 'linked' => true],
        ]];
    }

    /**
     * @param array<string, array<string, mixed>>|null $price
     */
    private function insertProduct(string $key, ?array $price, string $versionId = Defaults::LIVE_VERSION): void
    {
        $this->connection->insert('product', $this->productRow($key, $price, $versionId));
    }

    /**
     * @param array<string, array<string, mixed>>|null $price
     *
     * @return array<string, mixed>
     */
    private function productRow(string $key, ?array $price, string $versionId = Defaults::LIVE_VERSION): array
    {
        return [
            'id' => $this->ids->getBytes($key),
            'version_id' => Uuid::fromHexToBytes($versionId),
            'product_number' => $this->ids->get($key) . $versionId,
            'stock' => 10,
            'price' => $price === null ? null : json_encode($price, \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $price
     */
    private function insertAdvancedPrice(string $key, string $product, array $price): void
    {
        $this->connection->insert('rule', [
            'id' => $this->ids->getBytes('rule'),
            'name' => 'rule',
            'priority' => 1,
            'created_at' => '2021-01-01 00:00:00.000',
        ]);
        $this->connection->insert('product_price', [
            'id' => $this->ids->getBytes($key),
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'rule_id' => $this->ids->getBytes('rule'),
            'product_id' => $this->ids->getBytes($product),
            'product_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'quantity_start' => 1,
            'price' => json_encode($price, \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR),
            'created_at' => '2021-01-01 00:00:00.000',
        ]);
    }

    private function clearIndexerQueue(): void
    {
        $this->connection->delete('system_config', ['configuration_key' => IndexerQueuer::INDEXER_KEY]);
    }

    private function fetchPercentage(string $table, string $key, string $currencyId = Defaults::CURRENCY, string $versionId = Defaults::LIVE_VERSION): mixed
    {
        return $this->fetchPrice($table, $key, $versionId)['c' . $currencyId]['percentage'] ?? null;
    }

    /**
     * @return array<string, array<string, mixed>>|null
     */
    private function fetchPrice(string $table, string $key, string $versionId = Defaults::LIVE_VERSION): ?array
    {
        $price = $this->connection->fetchOne(
            'SELECT `price` FROM `' . $table . '` WHERE `id` = :id AND `version_id` = :version',
            ['id' => $this->ids->getBytes($key), 'version' => Uuid::fromHexToBytes($versionId)]
        );
        static::assertNotFalse($price);

        return $price === null ? null : json_decode((string) $price, true, 512, \JSON_THROW_ON_ERROR);
    }
}
