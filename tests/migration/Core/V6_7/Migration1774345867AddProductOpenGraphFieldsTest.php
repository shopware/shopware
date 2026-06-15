<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1774345867AddProductOpenGraphFields;

/**
 * @internal
 */
#[CoversClass(Migration1774345867AddProductOpenGraphFields::class)]
class Migration1774345867AddProductOpenGraphFieldsTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1774345867, (new Migration1774345867AddProductOpenGraphFields())->getCreationTimestamp());
    }

    public function testCreationTimestamp(): void
    {
        $migration = new Migration1774345867AddProductOpenGraphFields();

        static::assertSame(1774345867, $migration->getCreationTimestamp());
    }

    public function testColumnsAndForeignKeyAreCreated(): void
    {
        $this->rollbackInheritanceColumn();

        $migration = new Migration1774345867AddProductOpenGraphFields();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'product', 'open_graph_media_id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'product', 'openGraphMedia'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'product_translation', 'og_title'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'product_translation', 'og_description'));
        static::assertTrue(TableHelper::indexExists($this->connection, 'product', 'fk.product.open_graph_media_id'));
    }

    public function testForeignKeyIsCreatedWhenMissing(): void
    {
        $migration = new Migration1774345867AddProductOpenGraphFields();
        $migration->update($this->connection);

        if (TableHelper::foreignKeyExists($this->connection, 'product', 'fk.product.open_graph_media_id')) {
            $this->connection->executeStatement('ALTER TABLE `product` DROP FOREIGN KEY `fk.product.open_graph_media_id`');
        }

        if (TableHelper::indexExists($this->connection, 'product', 'fk.product.open_graph_media_id')) {
            $this->connection->executeStatement('ALTER TABLE `product` DROP INDEX `fk.product.open_graph_media_id`');
        }

        $migration->update($this->connection);

        static::assertTrue(TableHelper::indexExists($this->connection, 'product', 'fk.product.open_graph_media_id'));
    }

    private function rollbackInheritanceColumn(): void
    {
        if (!TableHelper::columnExists($this->connection, 'product', 'openGraphMedia')) {
            return;
        }

        $this->connection->executeStatement('ALTER TABLE `product` DROP COLUMN `openGraphMedia`');
    }
}
