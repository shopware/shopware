<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1782423127AddAppContentSystemStyleOptionTable;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1782423127AddAppContentSystemStyleOptionTable::class)]
class Migration1782423127AddAppContentSystemStyleOptionTableTest extends TestCase
{
    private const TABLE = 'app_content_system_style_option';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1782423127, (new Migration1782423127AddAppContentSystemStyleOptionTable())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->dropStyleOptionTable();

        static::assertFalse(TableHelper::tableExists($this->connection, self::TABLE));

        $migration = new Migration1782423127AddAppContentSystemStyleOptionTable();

        // Idempotent: running twice must not fail
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, self::TABLE));

        $table = TableHelper::getTable($this->connection, self::TABLE);
        static::assertCount(7, $table->columns);

        foreach (['id', 'app_id', 'name', 'schema', 'hash', 'created_at', 'updated_at'] as $column) {
            static::assertTrue(TableHelper::columnExists($this->connection, self::TABLE, $column), \sprintf('Missing column "%s"', $column));
        }
    }

    public function testNameColumnIsUniquelyIndexed(): void
    {
        $this->dropStyleOptionTable();

        $migration = new Migration1782423127AddAppContentSystemStyleOptionTable();
        $migration->update($this->connection);

        $indexes = $this->connection->fetchAllAssociative('SHOW INDEXES FROM `app_content_system_style_option` WHERE `Column_name` = \'name\'');

        static::assertCount(1, $indexes);
        static::assertSame('0', (string) $indexes[0]['Non_unique']);
    }

    public function testAppForeignKeyCascadesOnDelete(): void
    {
        $this->dropStyleOptionTable();

        // Removing the app is the sole cleanup path for its style options, so the cascade is load-bearing
        $migration = new Migration1782423127AddAppContentSystemStyleOptionTable();
        $migration->update($this->connection);

        $rule = $this->connection->fetchOne(
            'SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_NAME = \'fk.app_content_system_style_option.app_id\'
             AND CONSTRAINT_SCHEMA = DATABASE()'
        );

        static::assertSame('CASCADE', $rule);
    }

    private function dropStyleOptionTable(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `app_content_system_style_option`;');
    }
}
