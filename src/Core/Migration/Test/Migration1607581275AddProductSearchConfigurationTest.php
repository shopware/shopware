<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\Migration1607581275AddProductSearchConfiguration;

class Migration1607581275AddProductSearchConfigurationTest extends TestCase
{
    use KernelTestBehaviour;

    private const GERMAN_LANGUAGE_NAME = 'Deutsch';

    private const ENGLISH_LANGUAGE_NAME = 'English';

    /**
     * @var Connection
     */
    private $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testMigrationCreatesTables(): void
    {
        $migration = new Migration1607581275AddProductSearchConfiguration();
        $migration->update($this->connection);

        static::assertNotFalse($this->connection->fetchColumn('SHOW TABLES LIKE "product_search_config"'));
        static::assertNotFalse($this->connection->fetchColumn('SHOW TABLES LIKE "product_search_config_field"'));
    }

    public function testRunTwoTimes(): void
    {
        $migration = new Migration1607581275AddProductSearchConfiguration();
        $migration->update($this->connection);

        static::assertNotFalse($this->connection->fetchColumn('SHOW TABLES LIKE "product_search_config"'));
        static::assertNotFalse($this->connection->fetchColumn('SHOW TABLES LIKE "product_search_config_field"'));

        $migration = new Migration1607581275AddProductSearchConfiguration();
        $migration->update($this->connection);

        static::assertNotFalse($this->connection->fetchColumn('SHOW TABLES LIKE "product_search_config"'));
        static::assertNotFalse($this->connection->fetchColumn('SHOW TABLES LIKE "product_search_config_field"'));
    }
}
