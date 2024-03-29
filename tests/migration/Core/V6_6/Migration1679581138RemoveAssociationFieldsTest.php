<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_6\Migration1679581138RemoveAssociationFields;

/**
 * @internal
 */
#[CoversClass(Migration1679581138RemoveAssociationFields::class)]
class Migration1679581138RemoveAssociationFieldsTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testUpdateMakesColumnNullable(): void
    {
        $existed = $this->columnExists();
        if (!$existed) {
            $this->addColumn();
        }

        $columns = $this->connection->fetchAllAssociativeIndexed('SHOW COLUMNS FROM `media_default_folder`');
        static::assertSame('NO', $columns['association_fields']['Null']);

        $migration = new Migration1679581138RemoveAssociationFields();
        $migration->update($this->connection);

        $columns = $this->connection->fetchAllAssociativeIndexed('SHOW COLUMNS FROM `media_default_folder`');
        static::assertSame('YES', $columns['association_fields']['Null']);

        if (!$existed) {
            $migration->updateDestructive($this->connection);
        } else {
            $this->connection->executeStatement('ALTER TABLE `media_default_folder` CHANGE `association_fields` `association_fields` JSON NOT NULL');
        }
    }

    public function testUpdateDoesNotAddColumnIfNotExisted(): void
    {
        $migration = new Migration1679581138RemoveAssociationFields();

        $existed = $this->columnExists();

        $tableData = null;
        if ($existed) {
            $tableData = $this->fetchData();
            $migration->updateDestructive($this->connection);
        }

        static::assertFalse($this->columnExists());

        $migration->update($this->connection);

        static::assertFalse($this->columnExists());

        if ($existed) {
            $this->addColumn();
            $this->restoreAssociations($tableData);
        }
    }

    public function testUpdateDestructiveRemovesColumn(): void
    {
        $existed = $this->columnExists();

        $tableData = null;
        if ($existed) {
            $tableData = $this->fetchData();
        } else {
            $this->addColumn();
        }

        $migration = new Migration1679581138RemoveAssociationFields();
        $migration->updateDestructive($this->connection);
        $migration->updateDestructive($this->connection);

        static::assertFalse($this->columnExists());

        if ($existed) {
            $this->addColumn();
            $this->restoreAssociations($tableData);
        }
    }

    /**
     * @return array<array<string, string>>
     */
    private function fetchData(): array
    {
        return $this->connection->fetchAllAssociative('SELECT * FROM media_default_folder');
    }

    /**
     * @param array<array<string, string>> $data
     */
    private function restoreAssociations(array $data): void
    {
        foreach ($data as $row) {
            $this->connection->update(
                'media_default_folder',
                ['association_fields' => $row['association_fields']],
                ['id' => $row['id']]
            );
        }
    }

    private function addColumn(): void
    {
        $this->connection->executeStatement(
            'ALTER TABLE `media_default_folder` ADD COLUMN `association_fields` JSON NOT NULL'
        );
    }

    private function columnExists(): bool
    {
        $exists = $this->connection->fetchOne(
            'SHOW COLUMNS FROM `media_default_folder` WHERE `Field` LIKE "association_fields"',
        );

        return !empty($exists);
    }
}
