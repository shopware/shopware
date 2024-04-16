<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Product;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use OpenSearch\Namespaces\IndicesNamespace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldDefinition;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\ElasticsearchOutdatedIndexDetector;
use Shopware\Elasticsearch\Product\CustomFieldUpdater;

/**
 * @internal
 */
#[CoversClass(CustomFieldUpdater::class)]
class CustomFieldUpdaterTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        static::assertSame([
            EntityWrittenContainerEvent::class => 'onNewCustomFieldCreated',
        ], CustomFieldUpdater::getSubscribedEvents());
    }

    public function testNotProductWritten(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->expects(static::never())
            ->method('allowIndexing');

        $connection = $this->createMock(Connection::class);

        $customFieldUpdater = new CustomFieldUpdater(
            $this->createMock(ElasticsearchOutdatedIndexDetector::class),
            $this->createMock(Client::class),
            $elasticsearchHelper,
            $connection
        );

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection(),
            []
        );

        $customFieldUpdater->onNewCustomFieldCreated($containerEvent);
    }

    public function testElasticsearchDisabled(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(false);

        $indexDetector = $this->createMock(ElasticsearchOutdatedIndexDetector::class);
        $indexDetector
            ->expects(static::never())
            ->method('getAllUsedIndices');

        $connection = $this->createMock(Connection::class);

        $customFieldUpdater = new CustomFieldUpdater(
            $indexDetector,
            $this->createMock(Client::class),
            $elasticsearchHelper,
            $connection
        );

        $event = new EntityWrittenEvent(CustomFieldDefinition::ENTITY_NAME, [], Context::createDefaultContext());

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $customFieldUpdater->onNewCustomFieldCreated($containerEvent);
    }

    public function testCustomFieldUpdatedChangesNothing(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $indexDetector = $this->createMock(ElasticsearchOutdatedIndexDetector::class);
        $indexDetector
            ->expects(static::never())
            ->method('getAllUsedIndices');

        $connection = $this->createMock(Connection::class);

        $customFieldUpdater = new CustomFieldUpdater(
            $indexDetector,
            $this->createMock(Client::class),
            $elasticsearchHelper,
            $connection
        );

        $writeResults = [
            new EntityWriteResult('test', ['name' => 'test', 'type' => 'text'], CustomFieldDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_UPDATE, new EntityExistence(null, ['foo' => 'bar'], true, false, false, [])),
        ];

        $event = new EntityWrittenEvent(CustomFieldDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $customFieldUpdater->onNewCustomFieldCreated($containerEvent);
    }

    public function testCustomFieldCreationDoesCreateThemInES(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $indexDetector = $this->createMock(ElasticsearchOutdatedIndexDetector::class);
        $indexDetector
            ->method('getAllUsedIndices')
            ->willReturn(['test']);

        $indices = $this->createMock(IndicesNamespace::class);
        $connection = $this->createMock(Connection::class);

        $deLang = Uuid::randomHex();
        $connection->expects(static::once())->method('fetchFirstColumn')->willReturn([
            Defaults::LANGUAGE_SYSTEM,
            $deLang,
        ]);

        $customFields = [
            'properties' => [
                $deLang => [
                    'type' => 'object',
                    'dynamic' => true,
                    'properties' => [
                        'test' => [
                            'type' => 'keyword',
                            'normalizer' => 'sw_lowercase_normalizer',
                            'fields' => [
                                'search' => ['type' => 'text'],
                                'ngram' => ['type' => 'text', 'analyzer' => 'sw_ngram_analyzer'],
                            ],
                        ],
                    ],
                ],
                Defaults::LANGUAGE_SYSTEM => [
                    'type' => 'object',
                    'dynamic' => true,
                    'properties' => [
                        'test' => [
                            'type' => 'keyword',
                            'normalizer' => 'sw_lowercase_normalizer',
                            'fields' => [
                                'search' => ['type' => 'text'],
                                'ngram' => ['type' => 'text', 'analyzer' => 'sw_ngram_analyzer'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $indices
            ->expects(static::once())
            ->method('putMapping')
            ->with([
                'index' => 'test',
                'body' => [
                    'properties' => [
                        'customFields' => $customFields,
                    ],
                    '_source' => [
                        'includes' => [
                            'id',
                        ],
                    ],
                ],
            ]);

        $indices
            ->method('get')
            ->willReturn([
                'test' => [
                    'mappings' => [
                        '_source' => [
                            'includes' => ['id'],
                        ],
                    ],
                ],
            ]);

        $client = $this->createMock(Client::class);
        $client->method('indices')->willReturn($indices);

        $customFieldUpdater = new CustomFieldUpdater(
            $indexDetector,
            $client,
            $elasticsearchHelper,
            $connection
        );

        $writeResults = [
            new EntityWriteResult('test', ['name' => 'test', 'type' => 'text'], CustomFieldDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_INSERT),
        ];

        $event = new EntityWrittenEvent(CustomFieldDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $customFieldUpdater->onNewCustomFieldCreated($containerEvent);
    }

    /**
     * @param array<mixed> $mapping
     */
    #[DataProvider('providerMapping')]
    public function testMapping(string $type, array $mapping): void
    {
        static::assertSame($mapping, CustomFieldUpdater::getTypeFromCustomFieldType($type));
    }

    /**
     * @return iterable<string, array{0: string, 1: array<mixed>}>
     */
    public static function providerMapping(): iterable
    {
        yield 'int' => [
            CustomFieldTypes::INT,
            [
                'type' => 'long',
            ],
        ];

        yield 'float' => [
            CustomFieldTypes::FLOAT,
            [
                'type' => 'double',
            ],
        ];

        yield 'bool' => [
            CustomFieldTypes::BOOL,
            [
                'type' => 'boolean',
            ],
        ];

        yield 'datetime' => [
            CustomFieldTypes::DATETIME,
            [
                'type' => 'date',
                'format' => 'yyyy-MM-dd HH:mm:ss.000||strict_date_optional_time||epoch_millis',
                'ignore_malformed' => true,
            ],
        ];

        yield 'json' => [
            CustomFieldTypes::JSON,
            [
                'type' => 'object',
                'dynamic' => true,
            ],
        ];

        yield 'unknown' => [
            'unknown',
            [
                'type' => 'keyword',
                'normalizer' => 'sw_lowercase_normalizer',
                'fields' => [
                    'search' => ['type' => 'text'],
                    'ngram' => ['type' => 'text', 'analyzer' => 'sw_ngram_analyzer'],
                ],
            ],
        ];
    }
}
