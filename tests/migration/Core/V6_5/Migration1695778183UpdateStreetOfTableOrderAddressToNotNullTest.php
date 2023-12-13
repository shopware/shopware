<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_5\Migration1695778183UpdateStreetOfTableCustomerAddressToNotNull;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1695778183UpdateStreetOfTableCustomerAddressToNotNull::class)]
class Migration1695778183UpdateStreetOfTableOrderAddressToNotNullTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        try {
            $this->connection->executeStatement(
                'ALTER TABLE `customer_address` MODIFY COLUMN `street` varchar(255) NULL'
            );

            $this->connection->executeStatement('UPDATE `customer_address` SET `street` = NULL LIMIT 1');
        } catch (\Throwable) {
        }
    }

    public function testUpdate(): void
    {
        $migration = new Migration1695778183UpdateStreetOfTableCustomerAddressToNotNull();

        $migration->update($this->connection);
        $migration->update($this->connection);

        $columnDefinition = $this->getColumnDefinition('customer_address', 'street');

        static::assertIsArray($columnDefinition);
        static::assertEquals('street', $columnDefinition['Field']);
        static::assertEquals('NO', $columnDefinition['Null']);
        static::assertEquals('varchar(255)', $columnDefinition['Type']);
    }

    /**
     * @return array<string, mixed>|false
     */
    private function getColumnDefinition(string $table, string $column)
    {
        return $this->connection->fetchAssociative(
            'SHOW COLUMNS FROM ' . $table . ' WHERE Field = :column',
            ['column' => $column],
        );
    }
}
