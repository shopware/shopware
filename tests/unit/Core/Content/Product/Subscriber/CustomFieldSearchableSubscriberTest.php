<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\SearchKeywordUpdater;
use Shopware\Core\Content\Product\Subscriber\CustomFieldSearchableSubscriber;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldDefinition;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[CoversClass(CustomFieldSearchableSubscriber::class)]
class CustomFieldSearchableSubscriberTest extends TestCase
{
    private MockObject&Connection $connection;

    private MockObject&MessageBusInterface $messageBus;

    private MockObject&SearchKeywordUpdater $searchKeywordUpdater;

    private CustomFieldSearchableSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->searchKeywordUpdater = $this->createMock(SearchKeywordUpdater::class);

        $this->subscriber = new CustomFieldSearchableSubscriber(
            $this->connection,
            $this->messageBus,
            $this->searchKeywordUpdater
        );
    }

    public function testGetSubscribedEvents(): void
    {
        $events = CustomFieldSearchableSubscriber::getSubscribedEvents();

        static::assertArrayHasKey(EntityWrittenContainerEvent::class, $events);
        static::assertSame('onCustomFieldWritten', $events[EntityWrittenContainerEvent::class]);
    }

    public function testOnCustomFieldWrittenIgnoresWhenSearchableNotChanged(): void
    {
        $context = Context::createDefaultContext();
        $customFieldId = Uuid::randomHex();

        $writeResult = new EntityWriteResult(
            $customFieldId,
            ['name' => 'test_field', 'type' => 'text'],
            CustomFieldDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_UPDATE
        );

        $event = new EntityWrittenEvent(CustomFieldDefinition::ENTITY_NAME, [$writeResult], $context);
        $containerEvent = new EntityWrittenContainerEvent($context, new NestedEventCollection([$event]), []);

        $this->connection->expects($this->never())->method('fetchOne');
        $this->connection->expects($this->never())->method('fetchFirstColumn');
        $this->messageBus->expects($this->never())->method('dispatch');
        $this->searchKeywordUpdater->expects($this->never())->method('reset');

        $this->subscriber->onCustomFieldWritten($containerEvent);
    }

    public function testOnCustomFieldWrittenIgnoresWhenNoSearchConfig(): void
    {
        $context = Context::createDefaultContext();
        $customFieldId = Uuid::randomHex();

        $writeResult = new EntityWriteResult(
            $customFieldId,
            ['name' => 'test_field', 'type' => 'text', 'searchable' => true],
            CustomFieldDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_UPDATE
        );

        $event = new EntityWrittenEvent(CustomFieldDefinition::ENTITY_NAME, [$writeResult], $context);
        $containerEvent = new EntityWrittenContainerEvent($context, new NestedEventCollection([$event]), []);

        $customFieldIdsBytes = Uuid::fromHexToBytesList([$customFieldId]);

        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->with(
                'SELECT 1 FROM product_search_config_field WHERE custom_field_id IN (:customFieldIds) LIMIT 1',
                ['customFieldIds' => $customFieldIdsBytes],
                ['customFieldIds' => ArrayParameterType::BINARY]
            )
            ->willReturn(false);

        $this->connection->expects($this->never())->method('fetchFirstColumn');
        $this->messageBus->expects($this->never())->method('dispatch');
        $this->searchKeywordUpdater->expects($this->never())->method('reset');

        $this->subscriber->onCustomFieldWritten($containerEvent);
    }

    public function testOnCustomFieldWrittenIgnoresWhenCustomFieldNotFound(): void
    {
        $context = Context::createDefaultContext();
        $customFieldId = Uuid::randomHex();

        $writeResult = new EntityWriteResult(
            $customFieldId,
            ['name' => 'test_field', 'type' => 'text', 'searchable' => true],
            CustomFieldDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_UPDATE
        );

        $event = new EntityWrittenEvent(CustomFieldDefinition::ENTITY_NAME, [$writeResult], $context);
        $containerEvent = new EntityWrittenContainerEvent($context, new NestedEventCollection([$event]), []);

        $customFieldIdsBytes = Uuid::fromHexToBytesList([$customFieldId]);

        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->willReturn(1);

        $this->connection->expects($this->once())
            ->method('fetchFirstColumn')
            ->with(
                'SELECT name FROM custom_field WHERE id IN (:customFieldIds)',
                ['customFieldIds' => $customFieldIdsBytes],
                ['customFieldIds' => ArrayParameterType::BINARY]
            )
            ->willReturn([]);

        $this->messageBus->expects($this->never())->method('dispatch');
        $this->searchKeywordUpdater->expects($this->never())->method('reset');

        $this->subscriber->onCustomFieldWritten($containerEvent);
    }

    public function testOnCustomFieldWrittenIgnoresWhenNoProductsFound(): void
    {
        $context = Context::createDefaultContext();
        $customFieldId = Uuid::randomHex();
        $customFieldName = 'test_field';

        $writeResult = new EntityWriteResult(
            $customFieldId,
            ['name' => $customFieldName, 'type' => 'text', 'searchable' => false],
            CustomFieldDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_UPDATE
        );

        $event = new EntityWrittenEvent(CustomFieldDefinition::ENTITY_NAME, [$writeResult], $context);
        $containerEvent = new EntityWrittenContainerEvent($context, new NestedEventCollection([$event]), []);

        $customFieldIdsBytes = Uuid::fromHexToBytesList([$customFieldId]);

        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->willReturn(1);

        $this->connection->expects($this->exactly(2))
            ->method('fetchFirstColumn')
            ->willReturnCallback(function ($sql, $params) use ($customFieldName) {
                if (str_contains($sql, 'SELECT name FROM custom_field')) {
                    return [$customFieldName];
                }

                return [];
            });

        $this->searchKeywordUpdater->expects($this->once())->method('reset');
        $this->messageBus->expects($this->never())->method('dispatch');

        $this->subscriber->onCustomFieldWritten($containerEvent);
    }

    public function testOnCustomFieldWrittenDispatchesMessagesWhenProductsFound(): void
    {
        $context = Context::createDefaultContext();
        $customFieldId = Uuid::randomHex();
        $customFieldName = 'test_field';
        $productId1 = Uuid::randomHex();
        $productId2 = Uuid::randomHex();
        $productId3 = Uuid::randomHex();

        $writeResult = new EntityWriteResult(
            $customFieldId,
            ['name' => $customFieldName, 'type' => 'text', 'searchable' => true],
            CustomFieldDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_UPDATE
        );

        $event = new EntityWrittenEvent(CustomFieldDefinition::ENTITY_NAME, [$writeResult], $context);
        $containerEvent = new EntityWrittenContainerEvent($context, new NestedEventCollection([$event]), []);

        $customFieldIdsBytes = Uuid::fromHexToBytesList([$customFieldId]);

        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->willReturn(1);

        $this->connection->expects($this->exactly(2))
            ->method('fetchFirstColumn')
            ->willReturnCallback(function ($sql, $params) use ($customFieldName, $productId1, $productId2, $productId3) {
                if (str_contains($sql, 'SELECT name FROM custom_field')) {
                    return [$customFieldName];
                }

                return [$productId1, $productId2, $productId3];
            });

        $this->searchKeywordUpdater->expects($this->once())->method('reset');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $this->subscriber->onCustomFieldWritten($containerEvent);
    }
}
