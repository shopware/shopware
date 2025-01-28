<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_6\Migration1736866790AddDocumentA11yMediaFileIdForDocumentTable;

/**
 * @internal
 */
#[CoversClass(Migration1736866790AddDocumentA11yMediaFileIdForDocumentTable::class)]
class Migration1736866790AddDocumentA11yMediaFileIdForDocumentTableTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testMigration(): void
    {
        $this->rollback();

        $this->migrate();
        $this->migrate();

        static::assertTrue($this->hasForeignKey());
    }

    private function migrate(): void
    {
        (new Migration1736866790AddDocumentA11yMediaFileIdForDocumentTable())->update($this->connection);
    }

    private function rollback(): void
    {
        if ($this->hasForeignKey()) {
            $this->connection->executeStatement('ALTER TABLE `document` DROP FOREIGN KEY `fk.document.document_a11y_media_file_id`');
        }
    }

    private function hasForeignKey(): bool
    {
        $manager = $this->connection->createSchemaManager();
        $columns = $manager->listTableForeignKeys('document');

        return (bool) \array_filter($columns, static fn (ForeignKeyConstraint $column) => $column->getForeignTableName() === 'media' && $column->getLocalColumns() === ['document_a11y_media_file_id'] && $column->getForeignColumns() === ['id']);
    }
}
