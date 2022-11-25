<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Product;

use OpenSearch\Client;
use OpenSearch\Namespaces\IndicesNamespace;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\System\CustomField\CustomFieldDefinition;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\ElasticsearchOutdatedIndexDetector;
use Shopware\Elasticsearch\Product\CustomFieldUpdater;

/**
 * @internal
 *
 * @covers \Shopware\Elasticsearch\Product\CustomFieldUpdater
 */
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

        $customFieldUpdater = new CustomFieldUpdater(
            $this->createMock(ElasticsearchOutdatedIndexDetector::class),
            $this->createMock(Client::class),
            $elasticsearchHelper
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

        $customFieldUpdater = new CustomFieldUpdater(
            $indexDetector,
            $this->createMock(Client::class),
            $elasticsearchHelper
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

        $customFieldUpdater = new CustomFieldUpdater(
            $indexDetector,
            $this->createMock(Client::class),
            $elasticsearchHelper
        );

        $writeResults = [
            new EntityWriteResult('test', ['name' => 'test', 'type' => 'text'], CustomFieldDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_UPDATE, new EntityExistence(null, ['1'], true, false, false, [])),
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
        $indices
            ->expects(static::once())
            ->method('putMapping')
            ->with([
                'index' => 'test',
                'body' => [
                    'properties' => [
                        'customFields' => [
                            'properties' => [
                                'test' => [
                                    'type' => 'text',
                                ],
                            ],
                        ],
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
            $elasticsearchHelper
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
     * @dataProvider providerMapping
     *
     * @param array<mixed> $mapping
     */
    public function testMapping(string $type, array $mapping): void
    {
        static::assertSame($mapping, CustomFieldUpdater::getTypeFromCustomFieldType($type));
    }

    /**
     * @return iterable<string, array{0: string, 1: array<mixed>}>
     */
    public function providerMapping(): iterable
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
                'format' => 'yyyy-MM-dd HH:mm:ss.000',
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
            ],
        ];
    }
}
