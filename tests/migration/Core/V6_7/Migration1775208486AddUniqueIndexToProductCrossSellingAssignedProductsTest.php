<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Index\IndexType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1775208486AddUniqueIndexToProductCrossSellingAssignedProducts;

/**
 * @internal
 */
#[CoversClass(Migration1775208486AddUniqueIndexToProductCrossSellingAssignedProducts::class)]
class Migration1775208486AddUniqueIndexToProductCrossSellingAssignedProductsTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();

        $this->rollback();
    }

    protected function tearDown(): void
    {
        $this->rollback();

        parent::tearDown();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(
            1775208486,
            (new Migration1775208486AddUniqueIndexToProductCrossSellingAssignedProducts())->getCreationTimestamp()
        );
    }

    public function testMigrationRemovesExistingDuplicateAssignedProductsAndAddsUniqueIndex(): void
    {
        $mainProductId = Uuid::randomBytes();
        $assignedProductId = Uuid::randomBytes();
        $crossSellingId = Uuid::randomBytes();
        $olderAssignedId = Uuid::randomBytes();
        $newerAssignedId = Uuid::randomBytes();
        $liveVersionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        $queue = new MultiInsertQueryQueue($this->connection);

        $queue->addInsert('product', [
            'id' => $mainProductId,
            'stock' => 1,
            'version_id' => $liveVersionId,
        ]);

        $queue->addInsert('product', [
            'id' => $assignedProductId,
            'stock' => 1,
            'version_id' => $liveVersionId,
        ]);

        $queue->addInsert('product_cross_selling', [
            'id' => $crossSellingId,
            'product_id' => $mainProductId,
            'product_version_id' => $liveVersionId,
            'position' => 0,
            'sort_by' => ProductCrossSellingDefinition::SORT_BY_PRICE,
            'sort_direction' => FieldSorting::ASCENDING,
            'type' => ProductCrossSellingDefinition::TYPE_PRODUCT_LIST,
            'active' => 1,
            'created_at' => '2025-01-01 00:00:00.000',
        ]);

        $queue->addInsert('product_cross_selling_assigned_products', [
            'id' => $olderAssignedId,
            'cross_selling_id' => $crossSellingId,
            'product_id' => $assignedProductId,
            'product_version_id' => $liveVersionId,
            'position' => 1,
            'created_at' => '2025-01-01 00:00:00.000',
        ]);

        $queue->addInsert('product_cross_selling_assigned_products', [
            'id' => $newerAssignedId,
            'cross_selling_id' => $crossSellingId,
            'product_id' => $assignedProductId,
            'product_version_id' => $liveVersionId,
            'position' => 2,
            'created_at' => '2025-06-01 00:00:00.000',
        ]);

        $queue->execute();

        $countBefore = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM product_cross_selling_assigned_products WHERE cross_selling_id = :crossSellingId AND product_id = :productId',
            [
                'crossSellingId' => $crossSellingId,
                'productId' => $assignedProductId,
            ]
        );
        static::assertSame(2, $countBefore);

        $migration = new Migration1775208486AddUniqueIndexToProductCrossSellingAssignedProducts();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $countAfter = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM product_cross_selling_assigned_products WHERE cross_selling_id = :crossSellingId AND product_id = :productId',
            [
                'crossSellingId' => $crossSellingId,
                'productId' => $assignedProductId,
            ]
        );
        static::assertSame(1, $countAfter);

        $remainingId = $this->connection->fetchOne(
            'SELECT id FROM product_cross_selling_assigned_products WHERE cross_selling_id = :crossSellingId AND product_id = :productId',
            [
                'crossSellingId' => $crossSellingId,
                'productId' => $assignedProductId,
            ]
        );
        static::assertSame($newerAssignedId, $remainingId);

        static::assertTrue(
            TableHelper::indexExists(
                $this->connection,
                'product_cross_selling_assigned_products',
                'uniq.cross_selling_id__product_id__product_version_id'
            )
        );

        $index = TableHelper::getIndexOfTable(
            $this->connection,
            'product_cross_selling_assigned_products',
            'uniq.cross_selling_id__product_id__product_version_id'
        );
        static::assertSame(IndexType::UNIQUE->name, $index->type);
    }

    private function rollback(): void
    {
        if (TableHelper::indexExists(
            $this->connection,
            'product_cross_selling_assigned_products',
            'uniq.cross_selling_id__product_id__product_version_id'
        )) {
            $this->connection->executeStatement(
                'ALTER TABLE `product_cross_selling_assigned_products` DROP INDEX `uniq.cross_selling_id__product_id__product_version_id`'
            );
        }
    }
}
