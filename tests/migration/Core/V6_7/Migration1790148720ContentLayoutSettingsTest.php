<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1758018339ContentLayout;
use Shopware\Core\Migration\V6_7\Migration1790148720ContentLayoutSettings;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(Migration1790148720ContentLayoutSettings::class)]
class Migration1790148720ContentLayoutSettingsTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1790148720, (new Migration1790148720ContentLayoutSettings())->getCreationTimestamp());
    }

    public function testMigrationIsIdempotentAndAddsANullableJsonColumn(): void
    {
        $migration = new Migration1790148720ContentLayoutSettings();

        if (!TableHelper::tableExists($this->connection, 'content_layout')) {
            (new Migration1758018339ContentLayout())->update($this->connection);
        }

        if (TableHelper::columnExists($this->connection, 'content_layout', 'settings')) {
            $this->connection->executeStatement('ALTER TABLE `content_layout` DROP COLUMN `settings`');
        }
        static::assertFalse(TableHelper::columnExists($this->connection, 'content_layout', 'settings'));

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'content_layout', 'settings'));

        $column = $this->connection->fetchAssociative(
            'SELECT DATA_TYPE, IS_NULLABLE
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column',
            ['table' => 'content_layout', 'column' => 'settings']
        );

        static::assertIsArray($column);
        // MariaDB reports JSON columns as longtext with a json_valid check.
        static::assertContains(strtolower((string) $column['DATA_TYPE']), ['json', 'longtext']);
        static::assertSame('YES', $column['IS_NULLABLE']);
    }
}
