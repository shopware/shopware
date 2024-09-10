<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_6\Migration1673964565MigrateToReferencedColumns;

/**
 * @internal
 */
#[CoversClass(Migration1673964565MigrateToReferencedColumns::class)]
class Migration1673964565MigrateToReferencedColumnsTest extends TestCase
{
    private Connection $connection;

    private Migration1673964565MigrateToReferencedColumns $migration;

    protected function setUp(): void
    {
        $this->migration = new Migration1673964565MigrateToReferencedColumns();
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1673964565, $this->migration->getCreationTimestamp());
    }

    public function testUpdateDestructive(): void
    {
        $entityIdColumnExists = $this->entityIdColumnExists();
        if (!$entityIdColumnExists) {
            $this->addEntityIdColumn();
        }
        $referencedIdColumnHasGeneratedValue = $this->referenceColumnHasGeneratedValue('referenced_id');
        if (!$referencedIdColumnHasGeneratedValue) {
            $this->addGeneratedValueForReferencedId();
        }
        $referencedVersionIdColumnHasGeneratedValue = $this->referenceColumnHasGeneratedValue('referenced_version_id');
        if (!$referencedVersionIdColumnHasGeneratedValue) {
            $this->addGeneratedValueForReferencedVersionId();
        }

        $this->migration->updateDestructive($this->connection);
        $this->migration->updateDestructive($this->connection);

        static::assertFalse($this->entityIdColumnExists());
        static::assertFalse($this->referenceColumnHasGeneratedValue('referenced_id'));
        static::assertFalse($this->referenceColumnHasGeneratedValue('referenced_version_id'));

        if ($entityIdColumnExists) {
            $this->addEntityIdColumn();
        }
        if ($referencedIdColumnHasGeneratedValue) {
            $this->addGeneratedValueForReferencedId();
        }
        if ($referencedVersionIdColumnHasGeneratedValue) {
            $this->addGeneratedValueForReferencedVersionId();
        }
    }

    private function entityIdColumnExists(): bool
    {
        return (bool) $this->connection->fetchOne(
            'SHOW COLUMNS
             FROM `state_machine_history`
             WHERE `Field` LIKE "entity_id"'
        );
    }

    private function addEntityIdColumn(): void
    {
        $this->connection->executeStatement(
            'ALTER TABLE `state_machine_history`
             ADD COLUMN `entity_id` JSON NOT NULL;'
        );
    }

    private function referenceColumnHasGeneratedValue(string $columnName): bool
    {
        return $this->connection->fetchOne(
            'SELECT EXTRA
             FROM information_schema.columns
             WHERE table_schema = :database
                AND table_name = \'state_machine_history\'
                AND COlUMN_NAME = :columnName',
            ['database' => $this->connection->getDatabase(), 'columnName' => $columnName]
        ) === 'STORED GENERATED';
    }

    private function addGeneratedValueForReferencedId(): void
    {
        $this->connection->executeStatement(
            'ALTER TABLE `state_machine_history`
             MODIFY COLUMN `referenced_id` BINARY(16)
             GENERATED ALWAYS AS (
                COALESCE(UNHEX(JSON_UNQUOTE(JSON_EXTRACT(`entity_id`, \'$.id\'))), 0x0)
             ) STORED;'
        );
    }

    private function addGeneratedValueForReferencedVersionId(): void
    {
        $this->connection->executeStatement(
            'ALTER TABLE `state_machine_history`
             MODIFY COLUMN `referenced_version_id` BINARY(16)
             GENERATED ALWAYS AS (
                COALESCE(UNHEX(JSON_UNQUOTE(JSON_EXTRACT(`entity_id`, \'$.version_id\'))), 0x0)
             ) STORED;'
        );
    }
}
