<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Index\IndexType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1776770187AddDocumentFileTable;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1776770187AddDocumentFileTable::class)]
class Migration1776770187AddDatabaseFileTableTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->connection->executeStatement(\sprintf('DROP TABLE IF EXISTS `%s`;', DocumentFileDefinition::ENTITY_NAME));
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1776770187, (new Migration1776770187AddDocumentFileTable())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        static::assertFalse(TableHelper::tableExists($this->connection, DocumentFileDefinition::ENTITY_NAME));

        $migration = new Migration1776770187AddDocumentFileTable();

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, DocumentFileDefinition::ENTITY_NAME));
        static::assertCount(6, TableHelper::getTable($this->connection, DocumentFileDefinition::ENTITY_NAME)->columns);
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, DocumentFileDefinition::ENTITY_NAME, 'fk.document_file.document_id'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, DocumentFileDefinition::ENTITY_NAME, 'fk.document_file.media_id'));

        static::assertSame(
            IndexType::UNIQUE->name,
            TableHelper::getIndexOfTable($this->connection, DocumentFileDefinition::ENTITY_NAME, 'uniq.document_file.media_id')->type
        );

        static::assertSame(
            IndexType::UNIQUE->name,
            TableHelper::getIndexOfTable($this->connection, DocumentFileDefinition::ENTITY_NAME, 'uniq.document_file.document_id__document_format')->type
        );
    }
}
