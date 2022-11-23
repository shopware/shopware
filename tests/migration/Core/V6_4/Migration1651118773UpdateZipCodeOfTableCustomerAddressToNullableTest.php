<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1651118773UpdateZipCodeOfTableCustomerAddressToNullable;

/**
 * @internal
 * @covers \Shopware\Core\Migration\V6_4\Migration1651118773UpdateZipCodeOfTableCustomerAddressToNullable
 */
class Migration1651118773UpdateZipCodeOfTableCustomerAddressToNullableTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setup();
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigration(): void
    {
        $migration = new Migration1651118773UpdateZipCodeOfTableCustomerAddressToNullable();
        $migration->update($this->connection);

        /** @var array<string> */
        $dbUrlArr = parse_url($_SERVER['DATABASE_URL']);
        $databaseName = substr($dbUrlArr['path'], 1);
        $sql = "SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = '{$databaseName}' and TABLE_NAME = 'customer_address' and COLUMN_NAME = 'zipcode'";

        $columns = array_column($this->connection->fetchAllAssociative($sql), 'IS_NULLABLE');
        static::assertSame('YES', $columns[0]);
    }
}
