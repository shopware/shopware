<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\IndexerQueuer;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1791620738RemoveCheapestPriceAccessorOfParentProducts;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1791620738RemoveCheapestPriceAccessorOfParentProducts::class)]
class Migration1791620738RemoveCheapestPriceAccessorOfParentProductsTest extends TestCase
{
    use MigrationTestTrait;

    private const ACCESSOR = '{"ruledefault":{"currencyb7d2554b0ce847cd82f3ac9bd1c0dfca":{"gross":10,"net":10}}}';

    private Connection $connection;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->ids = new IdsCollection();
    }

    public function testUpdateRemovesTheAccessorOfParentsWithVariantsOnly(): void
    {
        $this->insertProduct('parent', childCount: 1);
        $this->insertProduct('variant', parent: 'parent');
        $this->insertProduct('standalone');

        (new Migration1791620738RemoveCheapestPriceAccessorOfParentProducts())->update($this->connection);

        static::assertNull($this->fetchAccessor('parent'));
        static::assertSame(self::ACCESSOR, $this->fetchAccessor('variant'));
        static::assertSame(self::ACCESSOR, $this->fetchAccessor('standalone'));
    }

    public function testUpdateRemovesTheAccessorOfMoreParentsThanOneBatchHolds(): void
    {
        // one parent more than the migration updates per batch
        $rows = [];
        $params = [];
        for ($i = 0; $i <= 1000; ++$i) {
            $rows[] = '(?, ?, ?, 10, 1, ?)';
            array_push(
                $params,
                $this->ids->getBytes('parent-' . $i),
                Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                $this->ids->get('parent-' . $i),
                self::ACCESSOR,
            );
        }
        $this->connection->executeStatement(
            'INSERT INTO `product` (`id`, `version_id`, `product_number`, `stock`, `child_count`, `cheapest_price_accessor`)
             VALUES ' . implode(', ', $rows),
            $params
        );

        (new Migration1791620738RemoveCheapestPriceAccessorOfParentProducts())->update($this->connection);

        static::assertSame(0, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `product` WHERE `child_count` > 0 AND `cheapest_price_accessor` IS NOT NULL'
        ));
    }

    public function testUpdateReindexesTheProductsWhenItRemovesAnAccessor(): void
    {
        $this->clearIndexerQueue();
        $this->insertProduct('parent', childCount: 1);

        (new Migration1791620738RemoveCheapestPriceAccessorOfParentProducts())->update($this->connection);

        static::assertSame(
            ['product.indexer' => []],
            (new IndexerQueuer($this->connection))->getIndexers()
        );
    }

    public function testUpdateReindexesNothingWhenNoAccessorIsLeftToRemove(): void
    {
        $this->insertProduct('parent', childCount: 1);
        $this->insertProduct('standalone');

        $migration = new Migration1791620738RemoveCheapestPriceAccessorOfParentProducts();
        $migration->update($this->connection);
        $this->clearIndexerQueue();
        $migration->update($this->connection);

        static::assertSame([], (new IndexerQueuer($this->connection))->getIndexers());
    }

    public function testUpdateTwice(): void
    {
        $this->insertProduct('parent', childCount: 1);
        $this->insertProduct('variant', parent: 'parent');

        $migration = new Migration1791620738RemoveCheapestPriceAccessorOfParentProducts();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertNull($this->fetchAccessor('parent'));
        static::assertSame(self::ACCESSOR, $this->fetchAccessor('variant'));
    }

    private function insertProduct(string $key, int $childCount = 0, ?string $parent = null): void
    {
        $this->connection->insert('product', [
            'id' => $this->ids->getBytes($key),
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'parent_id' => $parent !== null ? $this->ids->getBytes($parent) : null,
            'product_number' => $this->ids->get($key),
            'stock' => 10,
            'child_count' => $childCount,
            'cheapest_price_accessor' => self::ACCESSOR,
        ]);
    }

    private function clearIndexerQueue(): void
    {
        $this->connection->delete('system_config', ['configuration_key' => IndexerQueuer::INDEXER_KEY]);
    }

    private function fetchAccessor(string $key): ?string
    {
        $accessor = $this->connection->fetchOne(
            'SELECT `cheapest_price_accessor` FROM `product` WHERE `id` = :id AND `version_id` = :version',
            ['id' => $this->ids->getBytes($key), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)]
        );
        static::assertNotFalse($accessor);

        return $accessor;
    }
}
