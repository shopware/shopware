<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_6\Migration1734059427AddDocumentMediaFileIdsToDocumentTable;

/**
 * @internal
 */
#[CoversClass(Migration1734059427AddDocumentMediaFileIdsToDocumentTable::class)]
class Migration1734059427AddDocumentMediaFileIdsToDocumentTableTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigrate(): void
    {
        $this->rollback();
        $this->migrate();
        $this->migrate();

        $manager = $this->connection->createSchemaManager();
        $columns = $manager->listTableColumns('document');

        static::assertArrayHasKey('document_media_file_ids', $columns);
        static::assertFalse($columns['document_media_file_ids']->getNotnull());
    }

    private function migrate(): void
    {
        (new Migration1734059427AddDocumentMediaFileIdsToDocumentTable())->update($this->connection);
    }

    private function rollback(): void
    {
        $this->connection->executeStatement('ALTER TABLE `document` DROP COLUMN `document_media_file_ids`');
    }
}
