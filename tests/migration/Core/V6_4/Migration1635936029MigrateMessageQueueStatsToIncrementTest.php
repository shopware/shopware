<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Increment\IncrementGatewayRegistry;
use Shopware\Core\Framework\Test\MessageQueue\fixtures\FooMessage;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1635936029MigrateMessageQueueStatsToIncrement;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexingMessage;

/**
 * @internal
 * @covers \Shopware\Core\Migration\V6_4\Migration1635936029MigrateMessageQueueStatsToIncrement
 */
class Migration1635936029MigrateMessageQueueStatsToIncrementTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
        $this->rollback();
    }

    public function testMigration(): void
    {
        $this->connection->executeStatement('
            INSERT INTO `message_queue_stats`
            VALUES (:id1, :name1, :size1, :createdAt, :updatedAt), (:id2, :name2, :size2, :createdAt, :updatedAt)
        ', [
            'id1' => Uuid::randomBytes(),
            'id2' => Uuid::randomBytes(),
            'size1' => 2,
            'size2' => 5,
            'name1' => FooMessage::class,
            'name2' => ElasticsearchIndexingMessage::class,
            'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'updatedAt' => null,
        ]);

        $migration = new Migration1635936029MigrateMessageQueueStatsToIncrement();
        $migration->update($this->connection);

        $migrated = $this->connection->fetchAllAssociative('SELECT * FROM `increment` ORDER BY `count` DESC');

        static::assertEquals(IncrementGatewayRegistry::MESSAGE_QUEUE_POOL, $migrated[0]['pool']);
        static::assertEquals('message_queue_stats', $migrated[0]['cluster']);
        static::assertEquals(5, (int) $migrated[0]['count']);
        static::assertEquals(ElasticsearchIndexingMessage::class, $migrated[0]['key']);

        static::assertEquals(IncrementGatewayRegistry::MESSAGE_QUEUE_POOL, $migrated[1]['pool']);
        static::assertEquals('message_queue_stats', $migrated[1]['cluster']);
        static::assertEquals(2, (int) $migrated[1]['count']);
        static::assertEquals(FooMessage::class, $migrated[1]['key']);
    }

    private function rollback(): void
    {
        $this->connection->executeStatement('TRUNCATE `message_queue_stats`');
        $this->connection->executeStatement('TRUNCATE `increment`');
    }
}
