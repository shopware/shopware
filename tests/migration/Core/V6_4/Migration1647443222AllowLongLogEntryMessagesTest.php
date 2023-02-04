<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1647443222AllowLongLogEntryMessages;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1647443222AllowLongLogEntryMessages
 */
class Migration1647443222AllowLongLogEntryMessagesTest extends TestCase
{
    public function setUp(): void
    {
        /** @var Connection $connection */
        $connection = KernelLifecycleManager::getConnection();
        $connection->executeStatement('ALTER TABLE `log_entry` MODIFY COLUMN `message` VARCHAR(255) NOT NULL');
    }

    public function tearDown(): void
    {
        /** @var Connection $connection */
        $connection = KernelLifecycleManager::getConnection();
        (new Migration1647443222AllowLongLogEntryMessages())->update($connection);
    }

    public function testLongMessagesInLogEntriesCanBeWritten(): void
    {
        /** @var Connection $connection */
        $connection = KernelLifecycleManager::getConnection();

        (new Migration1647443222AllowLongLogEntryMessages())->update($connection);
        $connection->beginTransaction();

        $logEntryId = Uuid::randomBytes();
        // This string is now 1000 characters long, well beyond the old limit of 255 characters
        $longMessage = str_repeat('some-long-', 100);
        $payload = [
            'id' => $logEntryId,
            'message' => $longMessage,
            'level' => 500,
            'channel' => 'some-test-channel',
            'context' => json_encode([]),
            'extra' => json_encode([]),
            'updated_at' => null,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        $connection->insert('log_entry', $payload);

        /** @var array{message: string} $logEntry */
        $logEntry = $connection->fetchAssociative(
            'SELECT `message` FROM `log_entry` WHERE `id` = :id',
            ['id' => $logEntryId],
        );

        $connection->rollBack();
        static::assertEquals($longMessage, $logEntry['message']);
    }

    public function testLogEntryMessageColumnIsCorrect(): void
    {
        /** @var Connection $connection */
        $connection = KernelLifecycleManager::getConnection();

        (new Migration1647443222AllowLongLogEntryMessages())->update($connection);

        $this->checkLogEntryMessageColumnType($connection);
    }

    public function testMigrationCanRunMultipleTimes(): void
    {
        /** @var Connection $connection */
        $connection = KernelLifecycleManager::getConnection();

        (new Migration1647443222AllowLongLogEntryMessages())->update($connection);
        (new Migration1647443222AllowLongLogEntryMessages())->update($connection);

        $this->checkLogEntryMessageColumnType($connection);
    }

    private function checkLogEntryMessageColumnType(Connection $connection): void
    {
        /** @var array{DATA_TYPE: string} $messageColumn */
        $messageColumn = $connection->fetchAssociative('
            SELECT DATA_TYPE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE
             TABLE_SCHEMA = :dbName AND
             TABLE_NAME   = "log_entry" AND
             COLUMN_NAME  = "message"
        ', ['dbName' => $connection->getDatabase()]);

        static::assertEquals('longtext', $messageColumn['DATA_TYPE']);
    }
}
