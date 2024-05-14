<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Product;

use OpenSearch\Client;
use OpenSearch\Namespaces\IndicesNamespace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\ElasticsearchRegistry;
use Shopware\Elasticsearch\Product\ElasticsearchProductDefinition;
use Shopware\Elasticsearch\Product\LanguageSubscriber;

/**
 * @internal
 */
#[CoversClass(LanguageSubscriber::class)]
class LanguageSubscriberTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        static::assertSame([
            'language.written' => 'onLanguageWritten',
        ], LanguageSubscriber::getSubscribedEvents());
    }

    public function testOnLanguageWrittenWithoutEsEnabled(): void
    {
        $esHelper = $this->createMock(ElasticsearchHelper::class);
        $esHelper->expects(static::once())->method('allowIndexing')->willReturn(false);

        $subscriber = new LanguageSubscriber(
            $esHelper,
            $this->createMock(ElasticsearchRegistry::class),
            $this->createMock(Client::class),
        );

        $event = $this->createMock(EntityWrittenEvent::class);
        $event
            ->expects(static::never())
            ->method('getWriteResults');

        $subscriber->onLanguageWritten($event);
    }

    public function testOnLanguageWrittenWithoutEsDefinition(): void
    {
        $esHelper = $this->createMock(ElasticsearchHelper::class);
        $esHelper->expects(static::once())->method('allowIndexing')->willReturn(true);

        $writeResult = new EntityWriteResult(Uuid::randomHex(), [], OrderDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_UPDATE);

        $subscriber = new LanguageSubscriber(
            $esHelper,
            $this->createMock(ElasticsearchRegistry::class),
            $this->createMock(Client::class),
        );

        $event = $this->createMock(EntityWrittenEvent::class);
        $event
            ->expects(static::once())
            ->method('getWriteResults')->willReturn([$writeResult]);

        $subscriber->onLanguageWritten($event);
    }

    public function testOnLanguageWrittenWithoutInsertOperation(): void
    {
        $esHelper = $this->createMock(ElasticsearchHelper::class);
        $esHelper->expects(static::once())->method('allowIndexing')->willReturn(true);

        $writeResult = new EntityWriteResult(Uuid::randomHex(), [], ProductDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_UPDATE);
        $registry = $this->createMock(ElasticsearchRegistry::class);
        $registry->expects(static::never())->method('getDefinitions')->willReturn([new ProductDefinition()]);

        $subscriber = new LanguageSubscriber(
            $esHelper,
            $registry,
            $this->createMock(Client::class),
        );

        $event = $this->createMock(EntityWrittenEvent::class);
        $event
            ->expects(static::once())
            ->method('getWriteResults')->willReturn([$writeResult]);

        $subscriber->onLanguageWritten($event);
    }

    public function testOnLanguageWrittenWithoutExistingIndex(): void
    {
        $esHelper = $this->createMock(ElasticsearchHelper::class);
        $esHelper->expects(static::once())->method('allowIndexing')->willReturn(true);
        $esHelper->expects(static::once())->method('getIndexName')->willReturn('sw_product');

        $writeResult = new EntityWriteResult(Uuid::randomHex(), [], ProductDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_INSERT);
        $registry = $this->createMock(ElasticsearchRegistry::class);
        $esProductDefinition = $this->createMock(ElasticsearchProductDefinition::class);
        $esProductDefinition->expects(static::once())->method('getEntityDefinition')->willReturn(new ProductDefinition());
        $registry->expects(static::once())->method('getDefinitions')->willReturn([$esProductDefinition]);

        $client = $this->createMock(Client::class);
        $namespace = $this->createMock(IndicesNamespace::class);
        $namespace->expects(static::once())->method('exists')->with(['index' => 'sw_product'])->willReturn(false);

        $client->method('indices')->willReturn($namespace);

        $subscriber = new LanguageSubscriber(
            $esHelper,
            $registry,
            $client,
        );

        $event = $this->createMock(EntityWrittenEvent::class);
        $event
            ->expects(static::once())
            ->method('getWriteResults')->willReturn([$writeResult]);

        $subscriber->onLanguageWritten($event);
    }

    public function testOnLanguageWritten(): void
    {
        $esHelper = $this->createMock(ElasticsearchHelper::class);
        $esHelper->expects(static::once())->method('allowIndexing')->willReturn(true);
        $esHelper->expects(static::once())->method('getIndexName')->willReturn('sw_product');

        $writeResult = new EntityWriteResult(Uuid::randomHex(), [], LanguageDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_INSERT);
        $client = $this->createMock(Client::class);
        $registry = $this->createMock(ElasticsearchRegistry::class);
        $esProductDefinition = $this->createMock(ElasticsearchProductDefinition::class);
        $esProductDefinition->expects(static::once())->method('getEntityDefinition')->willReturn(new ProductDefinition());
        $esProductDefinition->expects(static::once())->method('getMapping')->willReturn([
            'properties' => [
                'field1' => 'test1',
                'field2' => 'test2',
            ],
        ]);
        $registry->expects(static::once())->method('getDefinitions')->willReturn([$esProductDefinition]);

        $namespace = $this->createMock(IndicesNamespace::class);
        $namespace->expects(static::once())->method('putMapping')->with([
            'index' => 'sw_product',
            'body' => [
                'properties' => [
                    'field1' => 'test1',
                    'field2' => 'test2',
                ],
            ],
        ]);

        $namespace->expects(static::once())->method('exists')->with(['index' => 'sw_product'])->willReturn(true);

        $client->method('indices')->willReturn($namespace);

        $subscriber = new LanguageSubscriber(
            $esHelper,
            $registry,
            $client,
        );

        $event = $this->createMock(EntityWrittenEvent::class);
        $event
            ->expects(static::once())
            ->method('getWriteResults')->willReturn([$writeResult]);

        $subscriber->onLanguageWritten($event);
    }
}
