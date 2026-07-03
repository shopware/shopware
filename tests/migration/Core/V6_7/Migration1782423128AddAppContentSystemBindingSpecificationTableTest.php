<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1782423128AddAppContentSystemBindingSpecificationTable;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1782423128AddAppContentSystemBindingSpecificationTable::class)]
class Migration1782423128AddAppContentSystemBindingSpecificationTableTest extends TestCase
{
    private const TABLE = 'app_content_system_binding_specification';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1782423128, (new Migration1782423128AddAppContentSystemBindingSpecificationTable())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->dropBindingSpecificationTable();

        static::assertFalse(TableHelper::tableExists($this->connection, self::TABLE));

        $migration = new Migration1782423128AddAppContentSystemBindingSpecificationTable();

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

    public function testAppIdAndNameAreUniquelyIndexedAsComposite(): void
    {
        $this->dropBindingSpecificationTable();

        $migration = new Migration1782423128AddAppContentSystemBindingSpecificationTable();
        $migration->update($this->connection);

        // Bindings are unique within their app, not globally, so the unique key must be the
        // composite (app_id, name) - not name alone, which is what the sibling style-option
        // table uses.
        $indexes = $this->connection->fetchAllAssociative('SHOW INDEXES FROM `app_content_system_binding_specification` WHERE `Key_name` = \'uniq.app_content_system_binding_specification.app_id_name\'');
        usort($indexes, static fn (array $a, array $b): int => $a['Seq_in_index'] <=> $b['Seq_in_index']);

        static::assertCount(2, $indexes);
        static::assertSame('0', (string) $indexes[0]['Non_unique']);
        static::assertSame('app_id', $indexes[0]['Column_name']);
        static::assertSame('name', $indexes[1]['Column_name']);
    }

    public function testNameColumnAloneIsNotUniquelyIndexed(): void
    {
        $this->dropBindingSpecificationTable();

        $migration = new Migration1782423128AddAppContentSystemBindingSpecificationTable();
        $migration->update($this->connection);

        $indexes = $this->connection->fetchAllAssociative('SHOW INDEXES FROM `app_content_system_binding_specification` WHERE `Column_name` = \'name\'');

        // The only index touching `name` is the composite (app_id, name) key, not a standalone one.
        static::assertCount(1, $indexes);
        static::assertSame('uniq.app_content_system_binding_specification.app_id_name', $indexes[0]['Key_name']);
    }

    public function testAppForeignKeyCascadesOnDelete(): void
    {
        $this->dropBindingSpecificationTable();

        // Removing the app is the sole cleanup path for its bindings, so the cascade is load-bearing
        $migration = new Migration1782423128AddAppContentSystemBindingSpecificationTable();
        $migration->update($this->connection);

        $rule = $this->connection->fetchOne(
            'SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_NAME = \'fk.app_content_system_binding_specification.app_id\'
             AND CONSTRAINT_SCHEMA = DATABASE()'
        );

        static::assertSame('CASCADE', $rule);
    }

    private function dropBindingSpecificationTable(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `app_content_system_binding_specification`;');
    }
}
