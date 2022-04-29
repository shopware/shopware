<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\Migration1651118773UpdateZipCodeOfTableCustomerAddressToNullable;

class Migration1651118773UpdateZipCodeOfTableCustomerAddressToNullableTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setup();
        $container = $this->getContainer();
        $this->connection = $container->get(Connection::class);
    }

    /**
     * @before
     */
    public function initialise(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $migration = new Migration1651118773UpdateZipCodeOfTableCustomerAddressToNullable();
        $migration->update($connection);
    }

    public function testMigration(): void
    {
        $databaseName = substr(parse_url($_SERVER['DATABASE_URL'])['path'], 1);
        $sql = <<<SQL
            SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = '$databaseName' and TABLE_NAME = 'customer_address' and COLUMN_NAME = 'zipcode'
        SQL;

        $columns = array_column($this->connection->fetchAllAssociative($sql), 'IS_NULLABLE');
        static::assertSame('YES', $columns[0]);
    }
}
