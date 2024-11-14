<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_6\Migration1691662140MigrateAvailableStock;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[CoversClass(Migration1691662140MigrateAvailableStock::class)]
class Migration1691662140MigrateAvailableStockTest extends TestCase
{
    private Connection $connection;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->ids = new IdsCollection();

        try {
            $this->connection->executeStatement(
                'DELETE FROM `product`'
            );
        } catch (\Throwable) {
        }
    }

    protected function tearDown(): void
    {
        try {
            $this->connection->executeStatement(
                'DELETE FROM `product`'
            );
        } catch (\Throwable) {
        }
    }

    public function testStockMigration(): void
    {
        $expected = [
            [
                'id' => $this->ids->get('p1'),
                'stock' => '7',
                'available_stock' => '7',
            ],
            [
                'id' => $this->ids->get('p2'),
                'stock' => '6',
                'available_stock' => '6',
            ],
            [
                'id' => $this->ids->get('p3'),
                'stock' => '8',
                'available_stock' => '8',
            ],
            [
                'id' => $this->ids->get('p4'),
                'stock' => '10',
                'available_stock' => '10',
            ],
            [
                'id' => $this->ids->get('p5'),
                'stock' => '-6',
                'available_stock' => '-6',
            ],
        ];

        $migration = new Migration1691662140MigrateAvailableStock();

        $this->createProduct($this->ids->getBytes('p1'), 5, 7, true);
        $this->createProduct($this->ids->getBytes('p2'), 6, 6, true);
        $this->createProduct($this->ids->getBytes('p3'), 8, 8, false);
        $this->createProduct($this->ids->getBytes('p4'), 8, 10, false);
        $this->createProduct($this->ids->getBytes('p5'), 8, -6, true);

        $migration->update($this->connection);

        static::assertSame(
            $expected,
            $this->connection->fetchAllAssociative('SELECT LOWER(HEX(id)) as id, stock, available_stock FROM product ORDER BY created_at ASC')
        );

        $migration->update($this->connection);

        static::assertSame(
            $expected,
            $this->connection->fetchAllAssociative('SELECT LOWER(HEX(id)) as id, stock, available_stock FROM product ORDER BY created_at ASC')
        );
    }

    private function createProduct(string $id, int $stock, int $availableStock, bool $isCloseout): void
    {
        $product = [
            'id' => $id,
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'stock' => $stock,
            'available_stock' => $availableStock,
            'is_closeout' => (int) $isCloseout,
        ];

        $this->connection->insert('product', $product);
    }
}
