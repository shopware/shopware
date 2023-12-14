<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1692279790AppShippingMethod;
use Shopware\Core\Migration\V6_6\Migration1679581138RemoveAssociationFields;

/**
 * @internal
 */
#[CoversClass(Migration1692279790AppShippingMethod::class)]
class Migration1692279790AppShippingMethodTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        parent::setUp();
    }

    /**
     * @throws Exception
     */
    public function testMigration(): void
    {
        if (!$this->columnExists($this->connection, 'media_default_folder', 'association_fields')) {
            $this->connection->executeStatement('ALTER TABLE `media_default_folder` ADD COLUMN `association_fields` JSON NOT NULL;');
        }

        $sql = 'SHOW TABLES LIKE "app_shipping_method"';

        $this->connection->executeStatement('DROP TABLE IF EXISTS `app_shipping_method`');
        static::assertSame(0, $this->connection->executeQuery($sql)->rowCount());

        $migration = new Migration1692279790AppShippingMethod();
        $migration->update($this->connection);

        static::assertSame(1, $this->connection->executeQuery($sql)->rowCount());

        $migration = new Migration1679581138RemoveAssociationFields();
        $migration->updateDestructive($this->connection);
    }

    public function testColumns(): void
    {
        $appShippingMethodColumns = array_column($this->connection->fetchAllAssociative('SHOW COLUMNS FROM `app_shipping_method`'), 'Field');

        static::assertContains('id', $appShippingMethodColumns);
        static::assertContains('app_id', $appShippingMethodColumns);
        static::assertContains('app_name', $appShippingMethodColumns);
        static::assertContains('shipping_method_id', $appShippingMethodColumns);
        static::assertContains('identifier', $appShippingMethodColumns);
        static::assertContains('created_at', $appShippingMethodColumns);
        static::assertContains('updated_at', $appShippingMethodColumns);
    }

    private function columnExists(Connection $connection, string $table, string $column): bool
    {
        $exists = $connection->fetchOne(
            'SHOW COLUMNS FROM `' . $table . '` WHERE `Field` LIKE :column',
            ['column' => $column]
        );

        return !empty($exists);
    }
}
