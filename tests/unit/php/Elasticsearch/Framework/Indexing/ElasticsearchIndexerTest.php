<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\Indexing;

use Doctrine\DBAL\Connection;
use Elasticsearch\Client;
use Elasticsearch\Namespaces\IndicesNamespace;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Elasticsearch\Exception\ElasticsearchIndexingException;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\ElasticsearchRegistry;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexingMessage;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchLanguageIndexIteratorMessage;
use Shopware\Elasticsearch\Framework\Indexing\IndexCreator;
use Shopware\Elasticsearch\Framework\Indexing\IndexerOffset;
use Shopware\Elasticsearch\Framework\Indexing\IndexingDto;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 *
 * @covers \Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexer
 */
class ElasticsearchIndexerTest extends TestCase
{
    /**
     * @var Connection&MockObject
     */
    private $connection;

    /**
     * @var ElasticsearchHelper&MockObject
     */
    private $helper;

    private ElasticsearchRegistry $registry;

    /**
     * @var IndexCreator&MockObject
     */
    private $indexCreator;

    /**
     * @var IteratorFactory&MockObject
     */
    private $iteratorFactory;

    /**
     * @var Client&MockObject
     */
    private $client;

    /**
     * @var EntityRepository&MockObject
     */
    private $currencyRepository;

    /**
     * @var EntityRepository&MockObject
     */
    private $languageRepository;

    /**
     * @var MessageBusInterface&MockObject
     */
    private $bus;

    private LanguageEntity $language1;

    private LanguageEntity $language2;

    /**
     * @var IndicesNamespace&MockObject
     */
    private $indices;

    public function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->helper = $this->createMock(ElasticsearchHelper::class);
        $this->registry = new ElasticsearchRegistry([$this->createDefinition('product')]);
        $this->indexCreator = $this->createMock(IndexCreator::class);
        $this->iteratorFactory = $this->createMock(IteratorFactory::class);
        $this->client = $this->createMock(Client::class);
        $this->currencyRepository = $this->createMock(EntityRepository::class);
        $this->languageRepository = $this->createMock(EntityRepository::class);
        $this->bus = $this->createMock(MessageBusInterface::class);

        $this->helper->method('allowIndexing')->willReturn(true);

        $this->language1 = new LanguageEntity();
        $this->language1->setId(Defaults::LANGUAGE_SYSTEM);
        $this->language1->setUniqueIdentifier(Defaults::LANGUAGE_SYSTEM);

        $this->language2 = new LanguageEntity();
        $this->language2->setId('2');
        $this->language2->setUniqueIdentifier('2');

        $this->languageRepository
            ->method('search')
            ->willReturn(new EntitySearchResult('language', 1, new LanguageCollection([$this->language1, $this->language2]), null, new Criteria(), Context::createDefaultContext()));

        $this->indices = $this->createMock(IndicesNamespace::class);
        $this->client->method('indices')->willReturn($this->indices);

        parent::setUp();
    }

    public function testHandleMessages(): void
    {
        static::assertSame(
            [
                ElasticsearchIndexingMessage::class,
                ElasticsearchLanguageIndexIteratorMessage::class,
            ],
            ElasticsearchIndexer::getHandledMessages()
        );
    }

    public function testIterateESDisabled(): void
    {
        $this->helper = $this->createMock(ElasticsearchHelper::class);
        $indexer = $this->getIndexer();

        static::assertNull($indexer->iterate(null), 'Iterate should return null if es is disabled');
    }

    public function testIterateNullCreatesIndices(): void
    {
        $indexer = $this->getIndexer();

        $this
            ->indexCreator
            ->expects(static::exactly(2))
            ->method('createIndex');

        static::assertNull($indexer->iterate(null));
    }

    public function testIterateNullCreatesIndicesAndIndexTaskInDB(): void
    {
        $indexer = $this->getIndexer();

        $this->connection
            ->expects(static::exactly(2))
            ->method('insert')
            ->with('elasticsearch_index_task');

        $this->indexCreator
            ->method('aliasExists')
            ->willReturn(true);

        $this
            ->indexCreator
            ->expects(static::exactly(2))
            ->method('createIndex');

        static::assertNull($indexer->iterate(null));
    }

    public function testIterateOffsetWithoutLanguageGetSkipped(): void
    {
        $indexer = $this->getIndexer();

        $this
            ->indexCreator
            ->expects(static::never())
            ->method('createIndex');

        $offset = new IndexerOffset([], [], null);

        static::assertNull($indexer->iterate($offset));
    }

    public function testIterateOffsetWithInvalidLanguage(): void
    {
        $indexer = $this->getIndexer();

        $this
            ->indexCreator
            ->expects(static::never())
            ->method('createIndex');

        $offset = new IndexerOffset(['invalid'], [], null);

        static::assertNull($indexer->iterate($offset));
    }

    public function testIterateWithMessage(): void
    {
        $indexer = $this->getIndexer();

        $query = $this->createMock(IterableQuery::class);
        $query->method('fetch')->willReturn(['1', '2']);

        $this->iteratorFactory
            ->method('createIterator')
            ->willReturn($query);

        $msg = $indexer->iterate(null);

        static::assertInstanceOf(ElasticsearchIndexingMessage::class, $msg);
        static::assertSame(Defaults::LANGUAGE_SYSTEM, $msg->getContext()->getLanguageId());
        static::assertSame(['1', '2'], $msg->getData()->getIds());
    }

    public function testIterateWithUnknownDefinition(): void
    {
        $indexer = $this->getIndexer();

        $query = $this->createMock(IterableQuery::class);
        $query->method('fetch')->willReturn(['1', '2']);

        $this->iteratorFactory
            ->method('createIterator')
            ->willReturn($query);

        $offset = new IndexerOffset([$this->language1->getId()], [$this->createDefinition('foo')], null);

        static::expectException(\RuntimeException::class);
        static::expectExceptionMessage('Definition foo not found');

        $msg = $indexer->iterate($offset);
    }

    public function testIterateWithMessageMultipleDefinitions(): void
    {
        $this->registry = new ElasticsearchRegistry([
            $this->createDefinition('product'),
            $this->createDefinition('category'),
        ]);

        $indexer = $this->getIndexer();

        $msg = $indexer->iterate(null);

        static::assertNull($msg);
    }

    public function testUpdateIdsESDisabled(): void
    {
        $this->helper = $this->createMock(ElasticsearchHelper::class);
        $this->helper
            ->expects(static::never())
            ->method('getIndexName');

        $indexer = $this->getIndexer();

        $indexer->updateIds($this->createMock(EntityDefinition::class), ['1', '2']);
    }

    public function testUpdateIndexDoesNotExistsCreatesThem(): void
    {
        $this
            ->indexCreator
            ->expects(static::exactly(2))
            ->method('createIndex');

        $indexer = $this->getIndexer();

        $indexer->updateIds($this->createMock(EntityDefinition::class), ['1', '2']);
    }

    public function testHandleInvalidMessage(): void
    {
        $this->helper = $this->createMock(ElasticsearchHelper::class);
        $this->helper
            ->expects(static::never())
            ->method('allowIndexing');

        $indexer = $this->getIndexer();

        $indexer->handle(new \ArrayObject());
    }

    public function testHandleESDisabled(): void
    {
        $this->helper = $this->createMock(ElasticsearchHelper::class);

        $this->connection->expects(static::never())->method('executeStatement');

        $indexer = $this->getIndexer();

        $indexer->handle(new ElasticsearchLanguageIndexIteratorMessage('1'));
    }

    public function testHandleLanguageInvalidLanguage(): void
    {
        $this->languageRepository = $this->createMock(EntityRepository::class);
        $this->languageRepository
            ->method('search')
            ->willReturn(new EntitySearchResult('language', 0, new LanguageCollection(), null, new Criteria(), Context::createDefaultContext()));

        $this->connection->expects(static::never())->method('executeStatement');

        $indexer = $this->getIndexer();

        $indexer->handle(new ElasticsearchLanguageIndexIteratorMessage('invalid'));
    }

    public function testHandleLanguageMessage(): void
    {
        $message = new ElasticsearchLanguageIndexIteratorMessage('1');

        $query = $this->createMock(IterableQuery::class);
        $query->method('fetch')->willReturnOnConsecutiveCalls(
            ['1', '2'],
            []
        );

        $this->iteratorFactory
            ->method('createIterator')
            ->willReturn($query);

        $this->indexCreator
            ->expects(static::once())
            ->method('createIndex');

        $indexer = $this->getIndexer();

        $indexer->handle($message);
    }

    public function testHandleIndexingInvalidDefinition(): void
    {
        $message = new ElasticsearchIndexingMessage(
            new IndexingDto([Uuid::randomHex()], 'foo', 'not_existing'),
            null,
            Context::createDefaultContext()
        );

        $this->indices
            ->expects(static::once())
            ->method('exists')->willReturn(true);

        $indexer = $this->getIndexer();

        static::expectException(\RuntimeException::class);
        static::expectExceptionMessage('Entity not_existing has no registered elasticsearch definition');

        $indexer->handle($message);
    }

    public function testHandleIndexing(): void
    {
        $productDefinition = $this->createDefinition('product');
        $productDefinition->method('fetch')
            ->willReturn([
                [
                    'id' => '1',
                    'name' => 'foo',
                    'description' => 'bar',
                    'price' => 10,
                    'stock' => 100,
                    'manufacturer' => [
                        'id' => '1',
                        'name' => 'foo',
                    ],
                ],
            ]);

        $this->registry = new ElasticsearchRegistry([$productDefinition]);

        $message = new ElasticsearchIndexingMessage(
            new IndexingDto([Uuid::randomHex()], 'foo', 'product'),
            null,
            Context::createDefaultContext()
        );

        $this->indices
            ->expects(static::once())
            ->method('exists')->willReturn(true);

        $indexer = $this->getIndexer();

        $indexer->handle($message);
    }

    public function testHandleIndexingFails(): void
    {
        $message = new ElasticsearchIndexingMessage(
            new IndexingDto([Uuid::randomHex()], 'foo', 'product'),
            null,
            Context::createDefaultContext()
        );

        $this->client->method('bulk')
            ->willReturn([
                'errors' => true,
                'items' => [
                    [
                        'index' => [
                            '_id' => '1',
                            '_index' => 'foo',
                            'status' => 200,
                        ],
                    ],
                    [
                        'index' => [
                            '_id' => '1',
                            '_index' => 'foo',
                            'status' => 400,
                            'error' => [
                                'type' => 'mapper_parsing_exception',
                                'reason' => 'failed to parse',
                            ],
                        ],
                    ],
                ],
            ]);

        $this->indices
            ->expects(static::once())
            ->method('exists')->willReturn(true);

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(static::once())
            ->method('error')
            ->with('failed to parse');

        $indexer = $this->getIndexer($logger);

        static::expectException(ElasticsearchIndexingException::class);

        $indexer->handle($message);
    }

    private function getIndexer(?LoggerInterface $logger = null): ElasticsearchIndexer
    {
        $logger = $logger ?? new NullLogger();

        return new ElasticsearchIndexer(
            $this->connection,
            $this->helper,
            $this->registry,
            $this->indexCreator,
            $this->iteratorFactory,
            $this->client,
            $logger,
            $this->currencyRepository,
            $this->languageRepository,
            new EventDispatcher(),
            1,
            $this->bus
        );
    }

    /**
     * @return AbstractElasticsearchDefinition&MockObject
     */
    private function createDefinition(string $name): AbstractElasticsearchDefinition
    {
        $es = $this->createMock(AbstractElasticsearchDefinition::class);

        $definition = $this->createMock(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn($name);

        $es->method('getEntityDefinition')->willReturn($definition);

        return $es;
    }
}
