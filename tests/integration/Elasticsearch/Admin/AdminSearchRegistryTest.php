<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Elasticsearch\Admin;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Elasticsearch\Admin\AdminElasticsearchHelper;
use Shopware\Elasticsearch\Admin\AdminIndexingBehavior;
use Shopware\Elasticsearch\Admin\AdminSearchRegistry;
use Shopware\Elasticsearch\Admin\Indexer\PromotionAdminSearchIndexer;
use Shopware\Elasticsearch\Test\AdminElasticsearchTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @package system-settings
 *
 * @internal
 */
#[Group('skip-paratest')]
class AdminSearchRegistryTest extends TestCase
{
    use AdminApiTestBehaviour;
    use AdminElasticsearchTestBehaviour;
    use KernelTestBehaviour;
    use QueueTestBehaviour;

    private Connection $connection;

    private AdminSearchRegistry $registry;

    private Client $client;

    protected function setUp(): void
    {
        $this->clearElasticsearch();

        $this->connection = $this->getContainer()->get(Connection::class);

        $this->client = $this->getContainer()->get(Client::class);

        $indexer = new PromotionAdminSearchIndexer(
            $this->connection,
            $this->getContainer()->get(IteratorFactory::class),
            $this->getContainer()->get('promotion.repository'),
            100
        );

        $searchHelper = new AdminElasticsearchHelper(true, true, 'sw-admin');
        $this->registry = new AdminSearchRegistry(
            ['promotion' => $indexer],
            $this->connection,
            $this->createMock(MessageBusInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->client,
            $searchHelper,
            [],
            []
        );
    }

    public function testIterate(): void
    {
        $c = $this->getContainer()->get(Connection::class);
        static::assertEmpty($c->fetchAllAssociative('SELECT `index` FROM `admin_elasticsearch_index_task`'));

        $this->registry->iterate(new AdminIndexingBehavior(true));

        $index = $c->fetchOne('SELECT `index` FROM `admin_elasticsearch_index_task`');

        static::assertNotFalse($index);

        static::assertTrue($this->client->indices()->exists(['index' => $index]));

        $indices = array_values($this->client->indices()->getMapping(['index' => $index]))[0];
        $properties = $indices['mappings']['properties'];

        $expectedProperties = [
            'id' => ['type' => 'keyword'],
            'text' => ['type' => 'text'],
            'entityName' => ['type' => 'keyword'],
            'parameters' => ['type' => 'keyword'],
            'textBoosted' => ['type' => 'text'],
        ];

        static::assertEquals($expectedProperties, $properties);
    }

    public function testRefresh(): void
    {
        $c = $this->getContainer()->get(Connection::class);
        static::assertEmpty($c->fetchAllAssociative('SELECT `index` FROM `admin_elasticsearch_index_task`'));

        $this->registry->refresh(new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([
            new EntityWrittenEvent('promotion', [
                new EntityWriteResult(
                    'c1a28776116d4431a2208eb2960ec340',
                    [],
                    'promotion',
                    EntityWriteResult::OPERATION_INSERT
                ),
            ], Context::createDefaultContext()),
        ]), []));

        $index = $c->fetchOne('SELECT `index` FROM `admin_elasticsearch_index_task`');

        static::assertNotFalse($index);

        static::assertTrue($this->client->indices()->exists(['index' => $index]));

        $indices = array_values($this->client->indices()->getMapping(['index' => $index]))[0];
        $properties = $indices['mappings']['properties'];

        $expectedProperties = [
            'id' => ['type' => 'keyword'],
            'text' => ['type' => 'text'],
            'entityName' => ['type' => 'keyword'],
            'parameters' => ['type' => 'keyword'],
            'textBoosted' => ['type' => 'text'],
        ];

        static::assertEquals($expectedProperties, $properties);
    }

    protected function getDiContainer(): ContainerInterface
    {
        return $this->getContainer();
    }
}
