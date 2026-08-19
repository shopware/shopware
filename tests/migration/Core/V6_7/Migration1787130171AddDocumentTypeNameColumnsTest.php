<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1787130171AddDocumentTypeNameColumns;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1787130171AddDocumentTypeNameColumns::class)]
class Migration1787130171AddDocumentTypeNameColumnsTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testUpdateAddsTypeNameColumns(): void
    {
        $this->dropTypeNameColumns();

        foreach (Migration1787130171AddDocumentTypeNameColumns::DOCUMENT_TYPE_TABLES as $table) {
            static::assertFalse(TableHelper::columnExists($this->connection, $table, 'type_name'));
        }

        $migration = new Migration1787130171AddDocumentTypeNameColumns();
        $migration->update($this->connection);
        $migration->update($this->connection);

        foreach (Migration1787130171AddDocumentTypeNameColumns::DOCUMENT_TYPE_TABLES as $table) {
            static::assertTrue(TableHelper::columnExists($this->connection, $table, 'type_name'));
        }
    }

    private function dropTypeNameColumns(): void
    {
        foreach (Migration1787130171AddDocumentTypeNameColumns::DOCUMENT_TYPE_TABLES as $table) {
            if (TableHelper::columnExists($this->connection, $table, 'type_name')) {
                $this->connection->executeStatement(\sprintf('ALTER TABLE `%s` DROP COLUMN `type_name`', $table));
            }
        }
    }
}
