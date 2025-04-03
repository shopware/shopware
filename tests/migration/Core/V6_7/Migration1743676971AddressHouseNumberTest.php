<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_7\Migration1743676971AddressHouseNumber;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1743676971AddressHouseNumber::class)]
class Migration1743676971AddressHouseNumberTest extends TestCase
{
    use KernelTestBehaviour;

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1743676971AddressHouseNumber();
        static::assertSame(1743676971, $migration->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $connection = self::getContainer()->get(Connection::class);

        $this->revertMigration($connection);

        $migration = new Migration1743676971AddressHouseNumber();
        $migration->update($connection);
        $migration->update($connection);

        $manager = $connection->createSchemaManager();

        $customerAddresscolumns = $manager->listTableColumns('customer_address');
        static::assertArrayHasKey('house_number', $customerAddresscolumns);
        static::assertFalse($customerAddresscolumns['house_number']->getNotnull());

        $orderAddressColumns = $manager->listTableColumns('order_address');
        static::assertArrayHasKey('house_number', $orderAddressColumns);
        static::assertFalse($orderAddressColumns['house_number']->getNotnull());

        $newsletterRecipientColumns = $manager->listTableColumns('newsletter_recipient');
        static::assertArrayHasKey('house_number', $newsletterRecipientColumns);
        static::assertFalse($newsletterRecipientColumns['house_number']->getNotnull());
    }

    private function revertMigration(Connection $connection): void
    {
        $manager = $connection->createSchemaManager();

        $customerAddresscolumns = $manager->listTableColumns('customer_address');
        if (\array_key_exists('house_number', $customerAddresscolumns)) {
            $connection->executeStatement('ALTER TABLE `customer_address` DROP COLUMN `house_number`');
        }

        $orderAddressColumns = $manager->listTableColumns('order_address');
        if (\array_key_exists('house_number', $orderAddressColumns)) {
            $connection->executeStatement('ALTER TABLE `order_address` DROP COLUMN `house_number`');
        }

        $newsletterRecipientColumns = $manager->listTableColumns('newsletter_recipient');
        if (\array_key_exists('house_number', $newsletterRecipientColumns)) {
            $connection->executeStatement('ALTER TABLE `newsletter_recipient` DROP COLUMN `house_number`');
        }
    }
}
