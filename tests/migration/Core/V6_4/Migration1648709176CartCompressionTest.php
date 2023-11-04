<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1648709176CartCompression;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1648709176CartCompression
 */
class Migration1648709176CartCompressionTest extends TestCase
{
    private const TEMPORARY_TABLE_NAME = 'cart_original';

    private const CREATE_TEST_TABLE_QUERY = <<<'EOF'
CREATE TABLE `cart` (
    `token` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `auto_increment` bigint NOT NULL AUTO_INCREMENT,
    `cart` longblob,
    PRIMARY KEY (`token`),
    UNIQUE KEY `auto_increment` (`auto_increment`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
EOF;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->renameTable('cart', self::TEMPORARY_TABLE_NAME);
        $this->connection->executeStatement(self::CREATE_TEST_TABLE_QUERY);
    }

    protected function tearDown(): void
    {
        $this->cleanUpAndRestore();
    }

    public function testAddCompressedAndPayloadColumns(): void
    {
        static::assertFalse($this->columnExists('compressed'));
        static::assertFalse($this->columnExists('payload'));
        $this->runMigration();
        static::assertTrue($this->columnExists('compressed'));
        static::assertTrue($this->columnExists('payload'));
    }

    public function testMoveDataToPayload(): void
    {
        $token = 'test_token';
        $data = 'test_cart_data';

        $this->connection->executeStatement(
            'INSERT INTO `cart` (`token`, `cart`) VALUES (:token, :cart)',
            ['token' => $token, 'cart' => $data]
        );

        static::assertEquals($data, $this->getValue($token, 'cart'));
        $this->runMigration();
        static::assertEquals('0', $this->getValue($token, 'compressed'));
        static::assertEquals($data, $this->getValue($token, 'payload'));
    }

    public function testMoveDataWithManyRows(): void
    {
        $numberOfRows = 1100;
        $values = [];
        for ($i = 0; $i < $numberOfRows; ++$i) {
            $values[] = "('test_token$i', 'test_cart_data')";
        }
        $this->connection->executeStatement('INSERT INTO `cart` (`token`, `cart`) VALUES ' . implode(', ', $values));

        $this->runMigration();

        static::assertEquals(0, $this->connection
            ->executeQuery('SELECT COUNT(1) FROM cart WHERE `payload` IS NULL')
            ->fetchOne());
    }

    public function testDropCartColumn(): void
    {
        static::assertTrue($this->columnExists('cart'));
        $this->runMigration();
        static::assertFalse($this->columnExists('cart'));
    }

    public function testMigrationCanBeRunTwice(): void
    {
        $this->runMigration();
        $this->runMigration();
        static::assertFalse($this->columnExists('cart'));
        static::assertTrue($this->columnExists('compressed'));
        static::assertTrue($this->columnExists('payload'));
    }

    private function runMigration(): void
    {
        $migration = new Migration1648709176CartCompression();
        $migration->updateDestructive($this->connection);
    }

    private function renameTable(string $oldTableName, string $newTableName): void
    {
        $this->connection->executeStatement("RENAME TABLE `$oldTableName` TO `$newTableName`");
    }

    private function columnExists(string $column): bool
    {
        return !empty($this->connection->fetchOne(
            'SHOW COLUMNS FROM `cart` WHERE `Field` LIKE :column',
            ['column' => $column]
        ));
    }

    private function cleanUpAndRestore(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `cart`');
        $this->renameTable(self::TEMPORARY_TABLE_NAME, 'cart');
    }

    private function getValue(string $token, string $column): mixed
    {
        $value = $this->connection->fetchOne(
            "SELECT `$column` FROM `cart` WHERE `token` = :token",
            ['token' => $token],
            ['tokens' => ParameterType::STRING]
        );

        return $value === false ? null : $value;
    }
}
