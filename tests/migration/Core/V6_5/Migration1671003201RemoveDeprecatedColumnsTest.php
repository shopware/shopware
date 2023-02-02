<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1671003201RemoveDeprecatedColumns;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_5\Migration1671003201RemoveDeprecatedColumns
 */
class Migration1671003201RemoveDeprecatedColumnsTest extends TestCase
{
    private Connection $connection;

    public function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        if ($this->getColumnInfo('user_access_key', 'write_access') === false) {
            $this->connection->executeStatement('
                ALTER TABLE user_access_key
                ADD `write_access` TINYINT(1) NOT NULL DEFAULT 0
            ');
        }

        if ($this->getColumnInfo('country', 'tax_free') === false) {
            $this->connection->executeStatement('
                ALTER TABLE country
                ADD `tax_free` TINYINT(1) NOT NULL DEFAULT 0
            ');
        }

        if ($this->getColumnInfo('country', 'company_tax_free') === false) {
            $this->connection->executeStatement('
                ALTER TABLE country
                ADD `company_tax_free` TINYINT(1) NOT NULL DEFAULT 0
            ');
        }

        if ($this->getColumnInfo('app_action_button', 'open_new_tab') === false) {
            $this->connection->executeStatement('
                ALTER TABLE app_action_button
                ADD `open_new_tab` TINYINT(1) NOT NULL DEFAULT 0
            ');
        }
    }

    public function testColumnDoesNotExist(): void
    {
        $migration = new Migration1671003201RemoveDeprecatedColumns();
        $migration->updateDestructive($this->connection);

        // can execute two times
        $migration->updateDestructive($this->connection);

        $this->assertColumnNotExists('user_access_key', 'write_access');

        $this->assertColumnNotExists('country', 'tax_free');
        $this->assertTriggerNotExists('country_tax_free_insert');
        $this->assertTriggerNotExists('country_tax_free_update');

        $this->assertColumnNotExists('country', 'company_tax_free');

        $this->assertColumnNotExists('app_action_button', 'open_new_tab');
    }

    private function assertColumnNotExists(string $table, string $column): void
    {
        $exists = $this->getColumnInfo($table, $column);

        static::assertFalse($exists, 'Failed asserting that column `' . $table . '`.`' . $column . '` does not exist');
    }

    private function assertTriggerNotExists(string $triggerName): void
    {
        $exists = $this->getTriggerInfo($triggerName);

        static::assertFalse($exists, 'Failed asserting that trigger `' . $triggerName . '` does not exist');
    }

    /**
     * @return array<string, mixed>|false
     */
    private function getColumnInfo(string $table, string $column): array|false
    {
        $database = $this->connection->fetchOne('SELECT DATABASE();');

        return $this->connection->fetchAssociative(
            '
                SELECT * FROM information_schema.`COLUMNS`
                WHERE TABLE_NAME = :table
                AND TABLE_SCHEMA = :database
                AND COLUMN_NAME = :column',
            [
                'table' => $table,
                'database' => $database,
                'column' => $column,
            ]
        );
    }

    /**
     * @return array<string, mixed>|false
     */
    private function getTriggerInfo(string $triggerName): array|false
    {
        $database = $this->connection->fetchOne('SELECT DATABASE();');

        return $this->connection->fetchAssociative(
            '
                SELECT * FROM information_schema.`TRIGGERS`
                WHERE TRIGGER_SCHEMA = :database
                AND TRIGGER_NAME = :trigger',
            [
                'database' => $database,
                'trigger' => $triggerName,
            ]
        );
    }
}
