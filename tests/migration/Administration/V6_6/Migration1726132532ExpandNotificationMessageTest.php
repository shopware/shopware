<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Administration\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Migration\V6_6\Migration1726132532ExpandNotificationMessage;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;

/**
 * @internal
 */
#[CoversClass(Migration1726132532ExpandNotificationMessage::class)]
class Migration1726132532ExpandNotificationMessageTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigration(): void
    {
        $this->connection->executeStatement('
            ALTER TABLE `notification` MODIFY `message` VARCHAR(255);
        ');

        $oldColumnType = $this->connection->fetchAssociative('SHOW COLUMNS FROM `notification` WHERE `Field` = "message"');
        static::assertIsArray($oldColumnType);
        static::assertArrayHasKey('Type', $oldColumnType);
        static::assertEquals('varchar(255)', $oldColumnType['Type']);

        $migration = new Migration1726132532ExpandNotificationMessage();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $newColumnType = $this->connection->fetchAssociative('SHOW COLUMNS FROM `notification` WHERE `Field` = "message"');
        static::assertIsArray($newColumnType);
        static::assertArrayHasKey('Type', $newColumnType);
        static::assertEquals('longtext', $newColumnType['Type']);
    }
}
