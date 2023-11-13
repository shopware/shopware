<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\Indexing;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use OpenSearch\Namespaces\IndicesNamespace;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\Indexing\CreateAliasTask;
use Shopware\Elasticsearch\Framework\Indexing\CreateAliasTaskHandler;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Shopware\Elasticsearch\Framework\Indexing\Event\ElasticsearchIndexAliasSwitchedEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 *
 * @covers \Shopware\Elasticsearch\Framework\Indexing\CreateAliasTaskHandler
 */
class CreateAliasTaskHandlerTest extends TestCase
{
    public function testHandledMessages(): void
    {
        $messages = CreateAliasTaskHandler::getHandledMessages();

        if ($messages instanceof \Traversable) {
            $messages = iterator_to_array($messages);
        }

        static::assertSame([CreateAliasTask::class], $messages);
    }

    public function testHandleLogsErrors(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(static::once())
            ->method('fetchAllAssociative')
            ->willThrowException(new \Exception('test'));

        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->expects(static::once())
            ->method('logAndThrowException');

        $handler = new CreateAliasTaskHandler(
            $this->createMock(EntityRepository::class),
            $this->createMock(Client::class),
            $connection,
            $elasticsearchHelper,
            [],
            new EventDispatcher(),
            $this->createMock(AbstractKeyValueStorage::class)
        );

        $handler->run();
    }

    public function testHandleEmptyTable(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(static::once())
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $client = $this->createMock(Client::class);
        $client->expects(static::never())->method('indices');

        $handler = new CreateAliasTaskHandler(
            $this->createMock(EntityRepository::class),
            $client,
            $connection,
            $this->createMock(ElasticsearchHelper::class),
            [],
            new EventDispatcher(),
            $this->createMock(AbstractKeyValueStorage::class)
        );

        $handler->run();
    }

    public function testHandleRun(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(static::once())
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'id' => 1,
                    'index' => 'index',
                    'alias' => 'alias',
                    'doc_count' => 1,
                ],
                [
                    'id' => 2,
                    'index' => 'second',
                    'alias' => 'second_alias',
                    'doc_count' => 0,
                ],
            ]);

        $connection
            ->expects(static::once())
            ->method('executeStatement');

        $client = $this->createMock(Client::class);
        $indices = $this->createMock(IndicesNamespace::class);

        $indices
            ->expects(static::once())
            ->method('putSettings')
            ->with([
                'index' => 'second',
                'body' => [
                    'number_of_replicas' => 1,
                    'refresh_interval' => null,
                ],
            ]);

        $indices
            ->expects(static::once())
            ->method('putAlias')
            ->with(['index' => 'second', 'name' => 'second_alias']);

        $client
            ->method('indices')
            ->willReturn($indices);

        $eventDispatcher = new EventDispatcher();

        $called = false;

        $eventDispatcher->addListener(ElasticsearchIndexAliasSwitchedEvent::class, function (ElasticsearchIndexAliasSwitchedEvent $event) use (&$called): void {
            $changes = $event->getChanges();
            static::assertArrayHasKey('second', $changes);

            static::assertSame('second_alias', $changes['second']);

            $called = true;
        });

        $storage = $this->createMock(AbstractKeyValueStorage::class);
        $storage->expects(static::once())->method('set')->with(ElasticsearchIndexer::ENABLE_MULTILINGUAL_INDEX_KEY, 1);

        $handler = new CreateAliasTaskHandler(
            $this->createMock(EntityRepository::class),
            $client,
            $connection,
            $this->createMock(ElasticsearchHelper::class),
            ['settings' => ['index' => ['number_of_replicas' => 1]]],
            $eventDispatcher,
            $storage
        );

        $handler->run();

        static::assertTrue($called, 'Event has not been fired');
    }

    public function testHandleRunSwapsAliasBecauseExists(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(static::once())
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'id' => 2,
                    'index' => 'index',
                    'alias' => 'alias',
                    'doc_count' => 0,
                ],
            ]);

        $connection
            ->expects(static::once())
            ->method('executeStatement');

        $client = $this->createMock(Client::class);
        $indices = $this->createMock(IndicesNamespace::class);

        $indices
            ->method('existsAlias')
            ->willReturn(true);

        $indices
            ->method('getAlias')
            ->willReturn([
                'old_index' => [
                    'alias',
                ],
            ]);

        $indices
            ->method('updateAliases')
            ->with([
                'body' => [
                    'actions' => [
                        [
                            'add' => [
                                'index' => 'index',
                                'alias' => 'alias',
                            ],
                        ],
                        [
                            'remove' => [
                                'index' => 'old_index',
                                'alias' => 'alias',
                            ],
                        ],
                    ],
                ],
            ]);

        $client
            ->method('indices')
            ->willReturn($indices);

        $handler = new CreateAliasTaskHandler(
            $this->createMock(EntityRepository::class),
            $client,
            $connection,
            $this->createMock(ElasticsearchHelper::class),
            ['settings' => ['index' => ['number_of_replicas' => 1]]],
            new EventDispatcher(),
            $this->createMock(AbstractKeyValueStorage::class)
        );

        $handler->run();
    }
}
