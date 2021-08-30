<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Migration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\IndexerQueuer;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class IndexerQueuerTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function setUp(): void
    {
        parent::setUp();

        $connection = $this->getContainer()->get(Connection::class);
        $connection->delete('system_config', ['configuration_key' => IndexerQueuer::INDEXER_KEY]);
    }

    public function testMultipleEntriesAreMerged(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        IndexerQueuer::registerIndexer($connection, 'test.indexer', ['test1']);
        IndexerQueuer::registerIndexer($connection, 'test.indexer', ['test2']);

        static::assertSame([
            'test.indexer' => ['test1', 'test2'],
        ], (new IndexerQueuer($connection))->getIndexers());
    }

    public function testOldEntriesGetsMerged(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $connection->insert('system_config', [
            'configuration_key' => IndexerQueuer::INDEXER_KEY,
            'configuration_value' => json_encode(['_value' => ['test.indexer' => 1]]),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        static::assertSame([
            'test.indexer' => [],
        ], (new IndexerQueuer($connection))->getIndexers());

        IndexerQueuer::registerIndexer($connection, 'test.indexer', ['test1']);

        static::assertSame([
            'test.indexer' => ['test1'],
        ], (new IndexerQueuer($connection))->getIndexers());
    }

    public function testOldEntriesCanBeFinished(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $connection->insert('system_config', [
            'configuration_key' => IndexerQueuer::INDEXER_KEY,
            'configuration_value' => json_encode(['_value' => ['test.indexer' => 1]]),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $queuer = new IndexerQueuer($connection);
        $queuer->finishIndexer(['test.indexer']);

        static::assertSame([], $queuer->getIndexers());
    }

    public function testWithOldEntriesAndNewCanBeFinished(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $connection->insert('system_config', [
            'configuration_key' => IndexerQueuer::INDEXER_KEY,
            'configuration_value' => json_encode(['_value' => ['test.indexer' => 1]]),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        IndexerQueuer::registerIndexer($connection, 'bla.indexer');

        $queuer = new IndexerQueuer($connection);
        $queuer->finishIndexer(['bla.indexer']);

        static::assertSame(['test.indexer' => []], $queuer->getIndexers());
    }

    public function testFinishCreatedEntries(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $queuer = new IndexerQueuer($connection);
        IndexerQueuer::registerIndexer($connection, 'test.indexer', ['test1']);
        static::assertNotSame([], $queuer->getIndexers());

        $queuer->finishIndexer(['test.indexer']);

        static::assertSame([], $queuer->getIndexers());
    }
}
