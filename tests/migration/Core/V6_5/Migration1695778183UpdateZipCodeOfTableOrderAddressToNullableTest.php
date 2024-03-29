<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_5\Migration1695776504UpdateZipCodeOfTableOrderAddressToNullable;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1695776504UpdateZipCodeOfTableOrderAddressToNullable::class)]
class Migration1695778183UpdateZipCodeOfTableOrderAddressToNullableTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        try {
            $this->connection->executeStatement(
                'ALTER TABLE `order_address` MODIFY COLUMN `zipcode` varchar(50) NOT NULL'
            );
        } catch (\Throwable) {
        }
    }

    public function testUpdate(): void
    {
        $migration = new Migration1695776504UpdateZipCodeOfTableOrderAddressToNullable();

        $migration->update($this->connection);
        $migration->update($this->connection);

        $columnDefinition = $this->getColumnDefinition('order_address', 'zipcode');

        static::assertIsArray($columnDefinition);
        static::assertEquals('zipcode', $columnDefinition['Field']);
        static::assertEquals('YES', $columnDefinition['Null']);
        static::assertEquals('varchar(50)', $columnDefinition['Type']);
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
