<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
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

    public function testUpdateDestructiveRemovesColumn(): void
    {
        $exists = $this->columnExists();

        if (!$exists) {
            $this->addColumn();
        }

        $migration = new Migration1737430168RemoveFileTypeOfDocumentTable();
        $migration->updateDestructive($this->connection);
        $migration->updateDestructive($this->connection);

        static::assertFalse($this->columnExists());

        if ($exists) {
            $this->addColumn();
        }
    }

    private function addColumn(): void
    {
        $this->connection->executeStatement(
            'ALTER TABLE `document` ADD COLUMN `file_type` VARCHAR(255) DEFAULT NULL'
        );
    }

    private function columnExists(): bool
    {
        $exists = $this->connection->fetchOne(
            'SHOW COLUMNS FROM `document` WHERE `Field` LIKE "file_type"',
        );

        return !empty($exists);
    }
}
