<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_8\Migration1751522543IncreaseProductWeightPrecision;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[CoversClass(Migration1751522543IncreaseProductWeightPrecision::class)]
class Migration1751522543IncreaseProductWeightPrecisionTest extends TestCase
{
    use MigrationTestTrait;

    private Migration1751522543IncreaseProductWeightPrecision $migration;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->migration = new Migration1751522543IncreaseProductWeightPrecision();
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function rollbackTransaction(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        if ($connection->isTransactionActive()) {
            $connection->rollBack();
        }
    }

    public function testMigration(): void
    {
        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        $columnInfo = $this->getColumnInfo('product', 'weight');
        
        static::assertSame('decimal(15,6) unsigned', $columnInfo['Type']);
        static::assertSame('YES', $columnInfo['Null']);
    }

    public function testWeightIsStoredCorrectlyAfterMigration(): void
    {
        $this->migration->update($this->connection);
        
        $productId = Uuid::randomBytes();
        $versionId = Uuid::randomBytes();
        $productNumber = Uuid::randomHex();
        
        // Test inserting data with higher precision after migration
        $this->connection->executeStatement(
            'INSERT INTO `product` (`id`, `version_id`, `product_number`, `weight`, `stock`, `created_at`) VALUES (?, ?, ?, ?, ?, ?)',
            [
                $productId,
                $versionId,
                $productNumber,
                123.456789,
                10,
                '2024-01-01 00:00:00.000'
            ]
        );
        
        $result = $this->connection->fetchAssociative(
            'SELECT weight FROM `product` WHERE `product_number` = ?',
            [$productNumber]
        );
        
        static::assertNotFalse($result);
        static::assertSame('123.456789', $result['weight']);
    }

    /**
     * @return array<string, mixed>
     */
    private function getColumnInfo(string $table, string $column): array
    {
        $result = $this->connection->fetchAssociative(
            'SHOW COLUMNS FROM `' . $table . '` WHERE Field = ?',
            [$column]
        );
        
        static::assertNotFalse($result, "Column '$column' not found in table '$table'");
        
        return $result;
    }
} 