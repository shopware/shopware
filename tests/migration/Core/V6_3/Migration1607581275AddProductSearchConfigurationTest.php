<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_3;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_3\Migration1607581275AddProductSearchConfiguration;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_3\Migration1607581275AddProductSearchConfiguration
 */
class Migration1607581275AddProductSearchConfigurationTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigrationCreatesTables(): void
    {
        $migration = new Migration1607581275AddProductSearchConfiguration();
        $migration->update($this->connection);

        static::assertNotFalse($this->connection->fetchOne('SHOW TABLES LIKE "product_search_config"'));
        static::assertNotFalse($this->connection->fetchOne('SHOW TABLES LIKE "product_search_config_field"'));
    }

    public function testRunTwoTimes(): void
    {
        $migration = new Migration1607581275AddProductSearchConfiguration();
        $migration->update($this->connection);

        static::assertNotFalse($this->connection->fetchOne('SHOW TABLES LIKE "product_search_config"'));
        static::assertNotFalse($this->connection->fetchOne('SHOW TABLES LIKE "product_search_config_field"'));

        $migration = new Migration1607581275AddProductSearchConfiguration();
        $migration->update($this->connection);

        static::assertNotFalse($this->connection->fetchOne('SHOW TABLES LIKE "product_search_config"'));
        static::assertNotFalse($this->connection->fetchOne('SHOW TABLES LIKE "product_search_config_field"'));
    }
}
