<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Test\Framework\Indexing;

use Doctrine\DBAL\Connection;
use Elasticsearch\Client;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Shopware\Elasticsearch\Test\ElasticsearchTestTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @group skip-paratest
 */
class ElasticsearchIndexerTest extends TestCase
{
    use ElasticsearchTestTestBehaviour;
    use KernelTestBehaviour;

    /**
     * @beforeClass
     * @afterClass
     */
    public static function clearElasticsearch(): void
    {
        $c = KernelLifecycleManager::getKernel()->getContainer();

        $client = $c->get(Client::class);

        $client->indices()->delete(['index' => '_all']);
        $client->indices()->refresh(['index' => '_all']);

        $connection = $c->get(Connection::class);
        $connection->executeStatement('DELETE FROM elasticsearch_index_task');
    }

    public function testFirstIndexDoesNotCreateTask(): void
    {
        $c = $this->getContainer()->get(Connection::class);
        static::assertEmpty($c->fetchAllAssociative('SELECT * FROM elasticsearch_index_task'));

        $indexer = $this->getContainer()->get(ElasticsearchIndexer::class);
        $indexer->iterate(null);

        static::assertEmpty($c->fetchAllAssociative('SELECT * FROM elasticsearch_index_task'));
    }

    /**
     * @depends testFirstIndexDoesNotCreateTask
     */
    public function testSecondIndexingCreatesTask(): void
    {
        $c = $this->getContainer()->get(Connection::class);
        static::assertEmpty($c->fetchAllAssociative('SELECT * FROM elasticsearch_index_task'));

        $indexer = $this->getContainer()->get(ElasticsearchIndexer::class);
        $indexer->iterate(null);

        static::assertNotEmpty($c->fetchAllAssociative('SELECT * FROM elasticsearch_index_task'));
    }

    protected function getDiContainer(): ContainerInterface
    {
        return $this->getContainer();
    }

    protected function runWorker(): void
    {
    }
}
