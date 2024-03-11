<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1692608216IncreaseAppConfigKeyColumnSize;

/**
 * @internal
 */
#[CoversClass(Migration1692608216IncreaseAppConfigKeyColumnSize::class)]
class Migration1692608216IncreaseAppConfigKeyColumnSizeTest extends TestCase
{
    public function testMigrationReturnsCorrectTimeStamp(): void
    {
        static::assertEquals(1692608216, (new Migration1692608216IncreaseAppConfigKeyColumnSize())->getCreationTimestamp());
    }

    public function testMigrationCanBeRunTwice(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $migration = new Migration1692608216IncreaseAppConfigKeyColumnSize();
        $migration->update($connection);

        $this->assertKeyColumnLength($connection, 255);

        $migration = new Migration1692608216IncreaseAppConfigKeyColumnSize();
        $migration->update($connection);

        $this->assertKeyColumnLength($connection, 255);
    }

    public function testKeyColumnSizeIsUpdated(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $connection->executeStatement('
            ALTER TABLE `app_config`
            MODIFY COLUMN `key` VARCHAR(50);
        ');
        $this->assertKeyColumnLength($connection, 50);

        $migration = new Migration1692608216IncreaseAppConfigKeyColumnSize();
        $migration->update($connection);

        $this->assertKeyColumnLength($connection, 255);
    }

    private function assertKeyColumnLength(Connection $connection, int $length): void
    {
        $data = $connection->fetchAssociative('SHOW COLUMNS FROM `app_config` WHERE `field` = \'key\'');
        static::assertIsArray($data);

        static::assertSame("varchar($length)", $data['Type']);
    }
}
