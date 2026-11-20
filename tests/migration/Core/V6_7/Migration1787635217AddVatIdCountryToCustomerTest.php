<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1787635217AddVatIdCountryToCustomer;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1787635217AddVatIdCountryToCustomer::class)]
class Migration1787635217AddVatIdCountryToCustomerTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    protected function tearDown(): void
    {
        // The column belongs to the schema every other test runs against, so put it back
        (new Migration1787635217AddVatIdCountryToCustomer())->update($this->connection);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1787635217, (new Migration1787635217AddVatIdCountryToCustomer())->getCreationTimestamp());
    }

    public function testTheColumnIsAddedAndTheMigrationIsRepeatable(): void
    {
        $this->rollback();

        $migration = new Migration1787635217AddVatIdCountryToCustomer();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'customer', 'vat_id_country_id'));
    }

    public function testTheColumnIsNullableSoExistingCustomersStayValid(): void
    {
        $this->rollback();

        (new Migration1787635217AddVatIdCountryToCustomer())->update($this->connection);

        $column = $this->connection->fetchAssociative(
            'SELECT `IS_NULLABLE`, `COLUMN_DEFAULT`
             FROM `information_schema`.`COLUMNS`
             WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = "customer" AND `COLUMN_NAME` = "vat_id_country_id"'
        );

        static::assertIsArray($column);
        static::assertSame('YES', $column['IS_NULLABLE']);
    }

    public function testTheColumnOnlyAcceptsAnExistingCountry(): void
    {
        $this->rollback();

        (new Migration1787635217AddVatIdCountryToCustomer())->update($this->connection);

        static::assertSame('fk.customer.vat_id_country_id', $this->connection->fetchOne(
            'SELECT `CONSTRAINT_NAME`
             FROM `information_schema`.`KEY_COLUMN_USAGE`
             WHERE `TABLE_SCHEMA` = DATABASE()
               AND `TABLE_NAME` = "customer"
               AND `COLUMN_NAME` = "vat_id_country_id"
               AND `REFERENCED_TABLE_NAME` = "country"'
        ));
    }

    public function testTheForeignKeyIsCreatedWhenAnEarlierRunOnlyGotAsFarAsTheColumn(): void
    {
        $this->rollback();

        (new Migration1787635217AddVatIdCountryToCustomer())->update($this->connection);

        // The state a run leaves behind when it adds the column and then fails on the constraint
        $this->dropForeignKey();

        (new Migration1787635217AddVatIdCountryToCustomer())->update($this->connection);

        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'customer', 'fk.customer.vat_id_country_id'));
    }

    private function rollback(): void
    {
        if (!TableHelper::columnExists($this->connection, 'customer', 'vat_id_country_id')) {
            return;
        }

        $this->dropForeignKey();
        $this->connection->executeStatement('ALTER TABLE `customer` DROP COLUMN `vat_id_country_id`;');
    }

    private function dropForeignKey(): void
    {
        if (!TableHelper::foreignKeyExists($this->connection, 'customer', 'fk.customer.vat_id_country_id')) {
            return;
        }

        $this->connection->executeStatement(
            'ALTER TABLE `customer` DROP FOREIGN KEY `fk.customer.vat_id_country_id`;'
        );
    }
}
