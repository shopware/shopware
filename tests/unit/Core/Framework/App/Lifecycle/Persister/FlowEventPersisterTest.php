<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Persister;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Flow\Event\Event;
use Shopware\Core\Framework\App\Flow\Event\Xml\CustomEvents;
use Shopware\Core\Framework\App\Lifecycle\Persister\FlowEventPersister;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(FlowEventPersister::class)]
class FlowEventPersisterTest extends TestCase
{
    private FlowEventPersister $flowEventPersister;

    private EntityRepository&MockObject $flowEventsRepositoryMock;

    private Connection&MockObject $connectionMock;

    protected function setUp(): void
    {
        $this->flowEventsRepositoryMock = $this->createMock(EntityRepository::class);
        $this->connectionMock = $this->createMock(Connection::class);
        $this->flowEventPersister = new FlowEventPersister($this->flowEventsRepositoryMock, $this->connectionMock);
    }

    public function testUpdateEvents(): void
    {
        $appId = Uuid::randomHex();
        $this->connectionMock->expects(static::once())->method('fetchAllKeyValue')->willReturnCallback(function ($sql, $params) use ($appId): array {
            static::assertSame('SELECT name, LOWER(HEX(id)) FROM app_flow_event WHERE app_id = :appId;', $sql);
            static::assertSame([
                'appId' => Uuid::fromHexToBytes($appId),
            ], $params);

            return ['swag.before.open_the_doors' => Uuid::fromHexToBytes($appId)];
        });

        $flowEventMock = $this->createMock(Event::class);
        $domDocument = new \DOMDocument();
        $domElement = $domDocument->createElement('root');
        $childElementLabel = $domDocument->createElement('flow-event', 'value');
        $childElementLabel->appendChild($domDocument->createElement('name', 'value'));
        $domElement->appendChild($childElementLabel);

        $customEventsMock = CustomEvents::fromXml($domElement);
        $flowEventMock->method('getCustomEvents')->willReturn($customEventsMock);

        $this->flowEventsRepositoryMock->expects(static::once())->method('upsert')->willReturnCallback(function ($upserts, $context) use ($appId): EntityWrittenContainerEvent {
            static::assertSame([
                [
                    'appId' => $appId,
                    'name' => 'value',
                    'aware' => [],
                ],
            ], $upserts);

            return new EntityWrittenContainerEvent($context, new NestedEventCollection(), []);
        });

        $this->flowEventsRepositoryMock->expects(static::once())->method('delete')->willReturnCallback(function ($ids, $context) use ($appId): EntityWrittenContainerEvent {
            static::assertSame([['id' => Uuid::fromHexToBytes($appId)]], $ids);

            return new EntityWrittenContainerEvent($context, new NestedEventCollection(), []);
        });

        $context = Context::createDefaultContext();
        $this->flowEventPersister->updateEvents($flowEventMock, $appId, $context, 'en-GB');
    }

    public function testUpdateEventsDeleteOldApp(): void
    {
        $appId = Uuid::randomHex();
        $this->connectionMock->expects(static::once())->method('fetchAllKeyValue')->willReturnCallback(function ($sql, $params) use ($appId): array {
            static::assertSame('SELECT name, LOWER(HEX(id)) FROM app_flow_event WHERE app_id = :appId;', $sql);
            static::assertSame([
                'appId' => Uuid::fromHexToBytes($appId),
            ], $params);

            return ['swag.before.open_the_doors' => Uuid::fromHexToBytes($appId)];
        });

        $flowEventMock = $this->createMock(Event::class);

        $domDocument = new \DOMDocument();
        $domElement = $domDocument->createElement('root');
        $childElementLabel = $domDocument->createElement('flow-event', 'value');
        $childElementLabel->appendChild($domDocument->createElement('name', 'swag.before.open_the_doors'));
        $domElement->appendChild($childElementLabel);

        $customEventsMock = CustomEvents::fromXml($domElement);
        $flowEventMock->method('getCustomEvents')->willReturn($customEventsMock);

        $this->flowEventsRepositoryMock->expects(static::once())->method('upsert')->willReturnCallback(function ($upserts, $context) use ($appId): EntityWrittenContainerEvent {
            static::assertSame([
                [
                    'appId' => $appId,
                    'name' => 'swag.before.open_the_doors',
                    'aware' => [],
                    'id' => Uuid::fromHexToBytes($appId),
                ],
            ], $upserts);

            return new EntityWrittenContainerEvent($context, new NestedEventCollection(), []);
        });

        $this->flowEventsRepositoryMock->expects(static::never())->method('delete');

        $context = Context::createDefaultContext();
        $this->flowEventPersister->updateEvents($flowEventMock, $appId, $context, 'en-GB');
    }

    public function testDeactivateFlow(): void
    {
        $appId = Uuid::randomHex();

        $this->connectionMock->expects(static::once())->method('executeStatement')->willReturnCallback(function ($sql, $params) use ($appId): void {
            static::assertSame('UPDATE `flow` SET `active` = false WHERE `event_name` IN (SELECT `name` FROM `app_flow_event` WHERE `app_id` = :appId);', $sql);
            static::assertSame([
                'appId' => Uuid::fromHexToBytes($appId),
            ], $params);
        });

        $this->flowEventPersister->deactivateFlow($appId);
    }
}
