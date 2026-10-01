<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1737430168RemoveFileTypeOfDocumentTable;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1737430168RemoveFileTypeOfDocumentTable::class)]
class Migration1737430168RemoveFileTypeOfDocumentTableTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1737430168, (new Migration1737430168RemoveFileTypeOfDocumentTable())->getCreationTimestamp());
    }

    public function testUpdateSetsColumnToNullable(): void
    {
        $exists = TableHelper::columnExists($this->connection, 'document', 'file_type');

        if (!$exists) {
            $this->addColumn();
        }

        $migration = new Migration1737430168RemoveFileTypeOfDocumentTable();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $fileTypeColumn = TableHelper::getColumnOfTable($this->connection, 'document', 'file_type');
        static::assertFalse($fileTypeColumn->isNotNull);

        if (!$exists) {
            $migration->updateDestructive($this->connection);
        }
    }

    public function testUpdateDestructiveRemovesColumn(): void
    {
        $exists = TableHelper::columnExists($this->connection, 'document', 'file_type');

        if (!$exists) {
            $this->addColumn();
        }

        $migration = new Migration1737430168RemoveFileTypeOfDocumentTable();
        $migration->updateDestructive($this->connection);
        $migration->updateDestructive($this->connection);

        static::assertFalse(TableHelper::columnExists($this->connection, 'document', 'file_type'));

        if ($exists) {
            $this->addColumn();
        }
    }

    private function addColumn(): void
    {
        $this->connection->executeStatement(
            'ALTER TABLE `document` ADD COLUMN `file_type` VARCHAR(255) NOT NULL'
        );
    }
}
