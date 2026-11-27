<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Subscriber\CustomFieldSearchableSubscriber;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\CachedSearchConfigLoader;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldDefinition;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(CustomFieldSearchableSubscriber::class)]
class CustomFieldSearchableSubscriberTest extends TestCase
{
    private MockObject&Connection $connection;

    private MockObject&CacheInvalidator $cacheInvalidator;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->cacheInvalidator = $this->createMock(CacheInvalidator::class);
    }

    public function testGetSubscribedEvents(): void
    {
        $this->connection->expects($this->never())->method('executeStatement');
        $this->cacheInvalidator->expects($this->never())->method('invalidate');

        $events = CustomFieldSearchableSubscriber::getSubscribedEvents();

        static::assertArrayHasKey(EntityWrittenContainerEvent::class, $events);
        static::assertSame('onCustomFieldWritten', $events[EntityWrittenContainerEvent::class]);
    }

    public function testOnCustomFieldWrittenReturnsEarlyWhenEsEnabled(): void
    {
        $subscriber = new CustomFieldSearchableSubscriber($this->connection, new ParameterBag(['elasticsearch.enabled' => true]), $this->cacheInvalidator);
        $context = Context::createDefaultContext();
        $customFieldId = Uuid::randomHex();

        $writeResult = new EntityWriteResult(
            $customFieldId,
            ['name' => 'test_field', 'type' => 'text', 'includeInSearch' => false],
            CustomFieldDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_UPDATE
        );

        $event = new EntityWrittenEvent(CustomFieldDefinition::ENTITY_NAME, [$writeResult], $context);
        $containerEvent = new EntityWrittenContainerEvent($context, new NestedEventCollection([$event]), []);

        $this->connection->expects($this->never())->method('executeStatement');
        $this->cacheInvalidator->expects($this->never())->method('invalidate');

        $subscriber->onCustomFieldWritten($containerEvent);
    }

    public function testOnCustomFieldWrittenIgnoresWhenSearchableNotInPayload(): void
    {
        $subscriber = new CustomFieldSearchableSubscriber($this->connection, new ParameterBag(), $this->cacheInvalidator);
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

        $this->connection->expects($this->never())->method('executeStatement');
        $this->cacheInvalidator->expects($this->never())->method('invalidate');

        $subscriber->onCustomFieldWritten($containerEvent);
    }

    public function testOnCustomFieldWrittenIgnoresWhenSearchableNotFalse(): void
    {
        $subscriber = new CustomFieldSearchableSubscriber($this->connection, new ParameterBag(), $this->cacheInvalidator);
        $context = Context::createDefaultContext();
        $customFieldId = Uuid::randomHex();

        $writeResult = new EntityWriteResult(
            $customFieldId,
            ['name' => 'test_field', 'type' => 'text', 'includeInSearch' => true],
            CustomFieldDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_UPDATE
        );

        $event = new EntityWrittenEvent(CustomFieldDefinition::ENTITY_NAME, [$writeResult], $context);
        $containerEvent = new EntityWrittenContainerEvent($context, new NestedEventCollection([$event]), []);

        $this->connection->expects($this->never())->method('executeStatement');
        $this->cacheInvalidator->expects($this->never())->method('invalidate');

        $subscriber->onCustomFieldWritten($containerEvent);
    }

    public function testOnCustomFieldWrittenDeletesFromProductSearchConfigField(): void
    {
        $subscriber = new CustomFieldSearchableSubscriber($this->connection, new ParameterBag(['elasticsearch.enabled' => false]), $this->cacheInvalidator);
        $context = Context::createDefaultContext();
        $customFieldId1 = Uuid::randomHex();
        $customFieldId2 = Uuid::randomHex();

        $writeResult1 = new EntityWriteResult(
            $customFieldId1,
            ['name' => 'test_field_1', 'type' => 'text', 'includeInSearch' => false],
            CustomFieldDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_UPDATE
        );

        $writeResult2 = new EntityWriteResult(
            $customFieldId2,
            ['name' => 'test_field_2', 'type' => 'text', 'includeInSearch' => false],
            CustomFieldDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_UPDATE
        );

        $event = new EntityWrittenEvent(CustomFieldDefinition::ENTITY_NAME, [$writeResult1, $writeResult2], $context);
        $containerEvent = new EntityWrittenContainerEvent($context, new NestedEventCollection([$event]), []);

        $customFieldIdsBytes = Uuid::fromHexToBytesList([$customFieldId1, $customFieldId2]);

        $this->connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                'DELETE FROM product_search_config_field
            WHERE custom_field_id IN (:customFieldIds)',
                ['customFieldIds' => $customFieldIdsBytes],
                ['customFieldIds' => ArrayParameterType::BINARY]
            );

        $this->cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with([CachedSearchConfigLoader::CACHE_KEY], true);

        $subscriber->onCustomFieldWritten($containerEvent);
    }
}
