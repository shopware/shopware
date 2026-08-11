<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1786378835AddDocumentBaseConfigFilenameInfixes;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1786378835AddDocumentBaseConfigFilenameInfixes::class)]
class Migration1786378835AddDocumentBaseConfigFilenameInfixesTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigrationAddsNullableColumn(): void
    {
        $this->rollback();

        static::assertFalse($this->columnExists());

        $migration = new Migration1786378835AddDocumentBaseConfigFilenameInfixes();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue($this->columnExists());

        $column = $this->connection
            ->createSchemaManager()
            ->introspectTableByUnquotedName(DocumentBaseConfigDefinition::ENTITY_NAME)
            ->getColumn('filename_infixes');

        static::assertFalse($column->getNotnull());
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(
            1786378835,
            (new Migration1786378835AddDocumentBaseConfigFilenameInfixes())->getCreationTimestamp()
        );
    }

    private function columnExists(): bool
    {
        return TableHelper::columnExists($this->connection, DocumentBaseConfigDefinition::ENTITY_NAME, 'filename_infixes');
    }

    private function rollback(): void
    {
        if (!$this->columnExists()) {
            return;
        }

        $this->connection->executeStatement('ALTER TABLE `document_base_config` DROP COLUMN `filename_infixes`');
    }
}
