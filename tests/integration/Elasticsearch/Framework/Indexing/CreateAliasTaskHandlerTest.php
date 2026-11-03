<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Elasticsearch\Framework\Indexing;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Framework\Indexing\CreateAliasTaskHandler;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Shopware\Elasticsearch\Test\ElasticsearchTestTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
#[Package('framework')]
class CreateAliasTaskHandlerTest extends TestCase
{
    use ElasticsearchTestTestBehaviour;
    use KernelTestBehaviour;

    protected function setUp(): void
    {
        $this->clearElasticsearch();
    }

    protected function tearDown(): void
    {
        $this->clearElasticsearch();
    }

    public function testTaskOfADeletedIndexDoesNotBlockOtherAliasSwitches(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $client = static::getContainer()->get(Client::class);
        $indexer = static::getContainer()->get(ElasticsearchIndexer::class);
        static::assertNotNull($indexer);

        $indexer->createIndices();
        $indexer->iterate(null);

        $task = $connection->fetchAssociative('SELECT `index`, `alias` FROM elasticsearch_index_task WHERE `entity` = :entity', ['entity' => 'product']);
        static::assertIsArray($task);
        $connection->executeStatement('UPDATE elasticsearch_index_task SET `doc_count` = 0');

        $connection->insert('elasticsearch_index_task', [
            'id' => Uuid::randomBytes(),
            '`entity`' => 'swag_deleted',
            '`index`' => 'swag_deleted_1',
            '`alias`' => 'swag_deleted',
            '`doc_count`' => 1,
        ]);

        static::getContainer()->get(CreateAliasTaskHandler::class)->run();

        static::assertSame([], $connection->fetchAllAssociative('SELECT * FROM elasticsearch_index_task'));
        static::assertSame([$task['index']], array_keys($client->indices()->getAlias(['name' => $task['alias']])));
    }

    protected function getDiContainer(): ContainerInterface
    {
        return static::getContainer();
    }

    protected function runWorker(): void
    {
    }
}
