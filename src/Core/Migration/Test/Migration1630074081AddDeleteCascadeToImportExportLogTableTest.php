<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1630074081AddDeleteCascadeToImportExportLogTable;

class Migration1630074081AddDeleteCascadeToImportExportLogTableTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    private Migration1630074081AddDeleteCascadeToImportExportLogTable $migration;

    public function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->getContainer()->get(Connection::class);
        $this->migration = new Migration1630074081AddDeleteCascadeToImportExportLogTable();

        $this->resetForeignKeyConstraintIfNotExists();
    }

    public function testUpdate(): void
    {
        $this->migration->update($this->connection);

        $foreignKeyName = $this->getFileForeignKeyName();

        static::assertIsString($foreignKeyName);
        static::assertNotEmpty($foreignKeyName);
    }

    private function resetForeignKeyConstraintIfNotExists(): void
    {
        $foreignKeyName = $this->getFileForeignKeyName();

        if ($foreignKeyName === null) {
            $this->connection->executeStatement('ALTER TABLE `import_export_log` ADD CONSTRAINT `fk.import_export_log.file_id` FOREIGN KEY (`file_id`) REFERENCES `import_export_file` (`id`) ON DELETE SET NULL;');
        }
    }

    private function getFileForeignKeyName(): ?string
    {
        $foreignKeyName = $this->connection->fetchOne(self::getForeignKeyQuery());

        if (\is_string($foreignKeyName) && !empty($foreignKeyName)) {
            return $foreignKeyName;
        }

        return null;
    }

    private static function getForeignKeyQuery(): string
    {
        return <<<'EOF'
SELECT `CONSTRAINT_NAME`
FROM `information_schema`.`KEY_COLUMN_USAGE`
WHERE
    `TABLE_NAME` = 'import_export_log' AND
    `REFERENCED_TABLE_NAME` = 'import_export_file' AND
    `COLUMN_NAME` = 'file_id' AND
    `REFERENCED_COLUMN_NAME` = 'id';
EOF;
    }
}
