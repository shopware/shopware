<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Event\MediaIndexerEvent;
use Shopware\Core\Content\Media\Subscriber\MediaWriteSubscriber;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MediaWriteSubscriber::class)]
class MediaWriteSubscriberTest extends TestCase
{
    public function testQueuedThumbnailWriteNotifiesItsOwnerAfterSuccess(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::USE_INDEXING_QUEUE);
        $context->addExtension(EntityIndexerRegistry::EXTENSION_INDEXER_SKIP, new ArrayEntity(['skips' => ['extension.option']]));
        $id = Uuid::randomHex();
        $command = static::createStub(InsertCommand::class);
        $command->method('getEntityName')->willReturn('media_thumbnail');
        $command->method('getPayload')->willReturn(['media_id' => Uuid::fromHexToBytes($id)]);
        $event = EntityWriteEvent::create(WriteContext::createFromContext($context), [$command]);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->never())->method('dispatch');
        $bus = $this->createMock(MessageBusInterface::class);
        $notification = new MediaIndexerEvent([$id], $context, ['extension.option']);
        $bus->expects($this->once())->method('dispatch')->with($notification)->willReturn(new Envelope($notification));

        (new MediaWriteSubscriber(static::createStub(Connection::class), $dispatcher, $bus))->beforeWrite($event);
        $event->success();
    }
}
