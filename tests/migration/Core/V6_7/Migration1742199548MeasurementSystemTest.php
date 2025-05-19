<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1742199548MeasurementSystem;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1742199548MeasurementSystem::class)]
class Migration1742199548MeasurementSystemTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $foreignKeys = [
            SalesChannelDomainDefinition::ENTITY_NAME => [
                'fk.sales_channel_domain.measurement_system_id',
                'fk.sales_channel_domain.weight_unit_id',
                'fk.sales_channel_domain.length_unit_id',
            ],
            SalesChannelDefinition::ENTITY_NAME => [
                'fk.sales_channel.measurement_system_id',
                'fk.sales_channel.weight_unit_id',
                'fk.sales_channel.length_unit_id',
            ],
        ];

        foreach ($foreignKeys as $table => $keys) {
            foreach ($keys as $key) {
                $exists = $this->connection->fetchOne(
                    'SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = :table AND CONSTRAINT_NAME = :key AND TABLE_SCHEMA = DATABASE()',
                    ['table' => $table, 'key' => $key]
                );

                if ((int) $exists > 0) {
                    $this->connection->executeStatement("ALTER TABLE `$table` DROP FOREIGN KEY `$key`");
                }
            }
        }

        $columns = [
            'sales_channel_domain' => ['measurement_system_id', 'weight_unit_id', 'length_unit_id'],
            'sales_channel' => ['measurement_system_id', 'weight_unit_id', 'length_unit_id'],
        ];

        foreach ($columns as $table => $cols) {
            foreach ($cols as $column) {
                $exists = $this->connection->fetchOne(
                    'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = :table AND COLUMN_NAME = :column AND TABLE_SCHEMA = DATABASE()',
                    ['table' => $table, 'column' => $column]
                );

                if ((int) $exists > 0) {
                    $this->connection->executeStatement("ALTER TABLE `$table` DROP COLUMN `$column`");
                }
            }
        }

        $this->connection->executeStatement('DROP TABLE IF EXISTS `measurement_display_unit_translation`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `measurement_display_unit`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `measurement_system_translation`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `measurement_system`');
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertEquals('1742199548', (new Migration1742199548MeasurementSystem())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $sm = $this->connection->createSchemaManager();

        static::assertFalse($sm->tablesExist(['measurement_system']));
        static::assertFalse($sm->tablesExist(['measurement_system_translation']));
        static::assertFalse($sm->tablesExist(['measurement_display_unit']));
        static::assertFalse($sm->tablesExist(['measurement_display_unit_translation']));

        $migration = new Migration1742199548MeasurementSystem();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue($sm->tablesExist(['measurement_system']));
        static::assertTrue($sm->tablesExist(['measurement_system_translation']));
        static::assertTrue($sm->tablesExist(['measurement_display_unit']));
        static::assertTrue($sm->tablesExist(['measurement_display_unit_translation']));
    }
}
