<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1692279790AppShippingMethod;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_5\Migration1692279790AppShippingMethod
 */
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
        Feature::skipTestIfActive('v6.6.0.0', $this);

        $sql = 'SHOW TABLES LIKE "app_shipping_method"';

        $this->connection->executeStatement('DROP TABLE IF EXISTS `app_shipping_method`');
        static::assertSame(0, $this->connection->executeQuery($sql)->rowCount());

        $migration = new Migration1692279790AppShippingMethod();
        $migration->update($this->connection);

        static::assertSame(1, $this->connection->executeQuery($sql)->rowCount());
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
}
