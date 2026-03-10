<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Subscriber;

use Doctrine\DBAL\Connection;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\Event\MediaThumbnailDeletedEvent;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Message\DeleteFileHandler;
use Shopware\Core\Content\Media\Message\DeleteFileMessage;
use Shopware\Core\Content\Media\Subscriber\MediaDeletionSubscriber;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\Stub\MessageBus\CollectingMessageBus;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MediaDeletionSubscriber::class)]
class MediaDeletionSubscriberTest extends TestCase
{
    private IdsCollection $ids;

    private CollectingEventDispatcher $dispatcher;

    private CollectingMessageBus $messageBus;

    private DeleteFileHandler $deleteFileHandler;

    private Filesystem $filesystemPublic;

    private Filesystem $filesystemPrivate;

    private Connection&MockObject $connection;

    /**
     * @var StaticEntityRepository<MediaThumbnailCollection>
     */
    private StaticEntityRepository $thumbnailRepository;

    /**
     * @var StaticEntityRepository<MediaCollection>
     */
    private StaticEntityRepository $mediaRepository;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->dispatcher = new CollectingEventDispatcher();
        $this->messageBus = new CollectingMessageBus();
        $this->filesystemPublic = new Filesystem(new InMemoryFilesystemAdapter(), ['public_url' => 'http://localhost:8000']);
        $this->filesystemPrivate = new Filesystem(new InMemoryFilesystemAdapter());
        $this->deleteFileHandler = new DeleteFileHandler($this->filesystemPublic, $this->filesystemPrivate);
        $this->connection = $this->createMock(Connection::class);
        $this->thumbnailRepository = new StaticEntityRepository([]);
        $this->mediaRepository = new StaticEntityRepository([]);
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            [EntityDeleteEvent::class => 'beforeDelete'],
            MediaDeletionSubscriber::getSubscribedEvents()
        );
    }

    public function testBeforeDeleteIgnoresUnrelatedEntities(): void
    {
        $event = EntityDeleteEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            []
        );
        $this->createMediaDeletionSubscriber()->beforeDelete($event);

        static::assertCount(0, $this->messageBus->getMessages());
    }

    public function testMediaDeletionDispatchesDeleteMessageForPublicFile(): void
    {
        $mediaId = $this->ids->get('media-1');
        $this->mediaRepository->addSearch(
            new MediaCollection([
                $this->createMediaEntity($mediaId, 'media/image.jpg', false),
            ])
        );

        $event = $this->createDeleteEvent(MediaDefinition::ENTITY_NAME, $mediaId);
        $this->createMediaDeletionSubscriber()->beforeDelete($event);

        $messages = $this->messageBus->getMessages();
        static::assertCount(1, $messages);
        static::assertInstanceOf(Envelope::class, \array_first($messages));

        $message = \array_first($messages)->getMessage();
        static::assertInstanceOf(DeleteFileMessage::class, $message);
        static::assertSame(['media/image.jpg'], $message->getFiles());
        static::assertSame('public', $message->getVisibility());
    }

    public function testMediaDeletionDispatchesDeleteMessageForPrivateFile(): void
    {
        $mediaId = $this->ids->get('media-1');
        $this->mediaRepository->addSearch(
            new MediaCollection([
                $this->createMediaEntity($mediaId, 'media/image.jpg', true),
            ])
        );

        $event = $this->createDeleteEvent(MediaDefinition::ENTITY_NAME, $mediaId);
        $this->createMediaDeletionSubscriber()->beforeDelete($event);

        $messages = $this->messageBus->getMessages();
        static::assertCount(1, $messages);
        static::assertInstanceOf(Envelope::class, \array_first($messages));

        $message = \array_first($messages)->getMessage();
        static::assertInstanceOf(DeleteFileMessage::class, $message);
        static::assertSame(['media/image.jpg'], $message->getFiles());
        static::assertSame('private', $message->getVisibility());
    }

    public function testMediaDeletionSkipsExternalUrls(): void
    {
        $mediaId = $this->ids->get('media-1');
        $this->mediaRepository->addSearch(
            new MediaCollection([
                $this->createMediaEntity($mediaId, 'https://localhost:8000/image.jpg', false),
            ])
        );

        $event = $this->createDeleteEvent(MediaDefinition::ENTITY_NAME, $mediaId);
        $this->createMediaDeletionSubscriber()->beforeDelete($event);

        static::assertCount(0, $this->messageBus->getMessages());
    }

    public function testMediaDeletionSkipsMediaWithoutFile(): void
    {
        $mediaId = $this->ids->get('media-1');
        $media = new MediaEntity();
        $media->setId($mediaId);
        $this->mediaRepository->addSearch(new MediaCollection([$media]));

        $event = $this->createDeleteEvent(MediaDefinition::ENTITY_NAME, $mediaId);
        $this->createMediaDeletionSubscriber()->beforeDelete($event);

        static::assertCount(0, $this->messageBus->getMessages());
    }

    public function testMediaDeletionDeletesThumbnailRecords(): void
    {
        $mediaId = $this->ids->get('media-1');
        $thumbId = $this->ids->get('thumb-1');

        $thumbnail = new MediaThumbnailEntity();
        $thumbnail->setId($thumbId);

        $this->mediaRepository->addSearch(
            new MediaCollection([
                $this->createMediaEntity(
                    $mediaId,
                    'media/image.jpg',
                    false,
                    new MediaThumbnailCollection([$thumbnail])
                ),
            ])
        );

        $event = $this->createDeleteEvent(MediaDefinition::ENTITY_NAME, $mediaId);
        $this->createMediaDeletionSubscriber()->beforeDelete($event);

        static::assertCount(1, $this->thumbnailRepository->deletes);
        static::assertSame([['id' => $thumbId]], \array_first($this->thumbnailRepository->deletes));
    }

    public function testMediaDeletionSkipsThumbnailDeleteWhenRemoteThumbnailsEnabled(): void
    {
        $mediaId = $this->ids->get('media-1');
        $thumbId = $this->ids->get('thumb-1');

        $thumbnail = new MediaThumbnailEntity();
        $thumbnail->setId($thumbId);

        $this->mediaRepository->addSearch(
            new MediaCollection([
                $this->createMediaEntity(
                    $mediaId,
                    'media/image.jpg',
                    false,
                    new MediaThumbnailCollection([$thumbnail])
                ),
            ])
        );

        $event = $this->createDeleteEvent(MediaDefinition::ENTITY_NAME, $mediaId);
        $this->createMediaDeletionSubscriber(remoteThumbnailsEnable: true)->beforeDelete($event);

        static::assertCount(0, $this->thumbnailRepository->deletes);
    }

    public function testMediaDeletionUsesSynchronousDeleteWhenStateSet(): void
    {
        $mediaId = $this->ids->get('media-1');

        $this->mediaRepository->addSearch(
            new MediaCollection([$this->createMediaEntity($mediaId, 'media/image.jpg', false)]),
        );
        $this->filesystemPublic->write('media/image.jpg', 'content');
        static::assertTrue($this->filesystemPublic->fileExists('media/image.jpg'));

        $context = Context::createDefaultContext();
        $context->addState(MediaDeletionSubscriber::SYNCHRONE_FILE_DELETE);

        $event = $this->createDeleteEvent(MediaDefinition::ENTITY_NAME, $mediaId, $context);
        $this->createMediaDeletionSubscriber()->beforeDelete($event);

        static::assertEmpty($this->messageBus->getMessages());
        static::assertFalse($this->filesystemPublic->fileExists('media/image.jpg'));
    }

    public function testThumbnailDeletionDispatchesDeleteMessageForPublicThumbnail(): void
    {
        $thumbId = $this->ids->get('thumb-1');
        $media = new MediaEntity();
        $media->setId($this->ids->get('media-1'));
        $media->setPrivate(false);

        $thumbnail = new MediaThumbnailEntity();
        $thumbnail->setId($thumbId);
        $thumbnail->setPath('thumbnail/thumbnail.jpg');
        $thumbnail->setMedia($media);

        $this->thumbnailRepository->addSearch(new MediaThumbnailCollection([$thumbnail]));

        $event = $this->createDeleteEvent(MediaThumbnailDefinition::ENTITY_NAME, $thumbId);
        $this->createMediaDeletionSubscriber()->beforeDelete($event);

        $messages = $this->messageBus->getMessages();
        static::assertCount(1, $messages);
        static::assertInstanceOf(Envelope::class, \array_first($messages));

        $message = \array_first($messages)->getMessage();
        static::assertInstanceOf(DeleteFileMessage::class, $message);
        static::assertSame(['thumbnail/thumbnail.jpg'], $message->getFiles());
        static::assertSame('public', $message->getVisibility());
    }

    public function testThumbnailDeletionDispatchesDeleteMessageForPrivateThumbnail(): void
    {
        $thumbId = $this->ids->get('thumb-1');
        $media = new MediaEntity();
        $media->setId($this->ids->get('media-1'));
        $media->setPrivate(true);

        $thumbnail = new MediaThumbnailEntity();
        $thumbnail->setId($thumbId);
        $thumbnail->setPath('thumbnail/thumbnail.jpg');
        $thumbnail->setMedia($media);

        $this->thumbnailRepository->addSearch(new MediaThumbnailCollection([$thumbnail]));

        $event = $this->createDeleteEvent(MediaThumbnailDefinition::ENTITY_NAME, $thumbId);
        $this->createMediaDeletionSubscriber()->beforeDelete($event);

        $messages = $this->messageBus->getMessages();
        static::assertCount(1, $messages);
        static::assertInstanceOf(Envelope::class, \array_first($messages));

        $message = \array_first($messages)->getMessage();
        static::assertInstanceOf(DeleteFileMessage::class, $message);
        static::assertSame(['thumbnail/thumbnail.jpg'], $message->getFiles());
        static::assertSame('private', $message->getVisibility());
    }

    public function testThumbnailDeletionSkipsExternalThumbnails(): void
    {
        $thumbId = $this->ids->get('thumb-1');
        $media = new MediaEntity();
        $media->setId($this->ids->get('media-1'));
        $media->setPrivate(false);

        $thumbnail = new MediaThumbnailEntity();
        $thumbnail->setId($thumbId);
        $thumbnail->setPath('https://localhost:8000/thumbnail.jpg');
        $thumbnail->setMedia($media);

        $this->thumbnailRepository->addSearch(new MediaThumbnailCollection([$thumbnail]));

        $event = $this->createDeleteEvent(MediaThumbnailDefinition::ENTITY_NAME, $thumbId);
        $this->createMediaDeletionSubscriber()->beforeDelete($event);

        static::assertCount(0, $this->messageBus->getMessages());
    }

    public function testThumbnailDeletionSkipsThumbnailWithoutMedia(): void
    {
        $thumbId = $this->ids->get('thumb-1');
        $thumbnail = new MediaThumbnailEntity();
        $thumbnail->setId($thumbId);
        $thumbnail->setPath('thumbnail/thumbnail.jpg');

        $this->thumbnailRepository->addSearch(new MediaThumbnailCollection([$thumbnail]));

        $event = $this->createDeleteEvent(MediaThumbnailDefinition::ENTITY_NAME, $thumbId);
        $this->createMediaDeletionSubscriber()->beforeDelete($event);

        static::assertCount(0, $this->messageBus->getMessages());
    }

    public function testThumbnailDeletionDispatchesThumbnailDeletedEvent(): void
    {
        $thumbId = $this->ids->get('thumb-1');
        $media = new MediaEntity();
        $media->setId($this->ids->get('media-1'));
        $media->setPrivate(false);

        $thumbnail = new MediaThumbnailEntity();
        $thumbnail->setId($thumbId);
        $thumbnail->setPath('thumbnail/thumbnail.jpg');
        $thumbnail->setMedia($media);

        $this->thumbnailRepository->addSearch(new MediaThumbnailCollection([$thumbnail]));

        $event = $this->createDeleteEvent(MediaThumbnailDefinition::ENTITY_NAME, $thumbId);
        $this->createMediaDeletionSubscriber()->beforeDelete($event);
        $event->success();

        $dispatchedEvents = $this->dispatcher->getEvents();
        static::assertArrayHasKey(MediaThumbnailDeletedEvent::EVENT_NAME, $dispatchedEvents);
        static::assertInstanceOf(MediaThumbnailDeletedEvent::class, $dispatchedEvents[MediaThumbnailDeletedEvent::EVENT_NAME]);
    }

    public function testFolderDeletionDeletesMediaInFolder(): void
    {
        $folderId = $this->ids->get('folder-1');
        $mediaId = $this->ids->get('media-1');

        $this->connection
            ->method('fetchFirstColumn')
            ->willReturn([]);
        $this->connection
            ->method('fetchAllAssociative')
            ->willReturn([['id' => $mediaId]]);

        $subscriber = $this->createMediaDeletionSubscriber();

        $event = $this->createDeleteEvent(MediaFolderDefinition::ENTITY_NAME, $folderId);
        $subscriber->beforeDelete($event);

        static::assertCount(1, $this->mediaRepository->deletes);
        static::assertSame([['id' => $mediaId]], $this->mediaRepository->deletes[0]);
    }

    public function testFolderDeletionRecursivelyFetchesChildFolders(): void
    {
        $folderId = $this->ids->get('folder-1');
        $childId = $this->ids->get('folder-2');
        $mediaId = $this->ids->get('media-1');

        $this->connection
            ->method('fetchFirstColumn')
            ->willReturnOnConsecutiveCalls([$childId], []);
        $this->connection
            ->method('fetchAllAssociative')
            ->willReturn([['id' => $mediaId]]);

        $event = $this->createDeleteEvent(MediaFolderDefinition::ENTITY_NAME, $folderId);
        $this->createMediaDeletionSubscriber()->beforeDelete($event);

        static::assertCount(1, $this->mediaRepository->deletes);
    }

    public function testFolderDeletionDoesNothingWhenNoMedia(): void
    {
        $folderId = $this->ids->get('folder-1');

        $this->connection
            ->method('fetchFirstColumn')
            ->willReturn([]);
        $this->connection
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $event = $this->createDeleteEvent(MediaFolderDefinition::ENTITY_NAME, $folderId);
        $this->createMediaDeletionSubscriber()->beforeDelete($event);

        static::assertCount(0, $this->mediaRepository->deletes);
    }

    private function createMediaDeletionSubscriber(bool $remoteThumbnailsEnable = false): MediaDeletionSubscriber
    {
        return new MediaDeletionSubscriber(
            $this->dispatcher,
            $this->thumbnailRepository,
            $this->messageBus,
            $this->deleteFileHandler,
            $this->connection,
            $this->mediaRepository,
            $remoteThumbnailsEnable,
        );
    }

    private function createMediaEntity(string $id, string $path, bool $private, ?MediaThumbnailCollection $thumbnails = null): MediaEntity
    {
        $media = new MediaEntity();
        $media->setId($id);
        $media->setPath($path);
        $media->setPrivate($private);
        $media->setThumbnails($thumbnails ?? new MediaThumbnailCollection());

        return $media;
    }

    private function createDeleteEvent(string $entityName, string $id, ?Context $context = null): EntityDeleteEvent
    {
        $definition = $this->getDefinitionForEntity($entityName);

        return EntityDeleteEvent::create(
            WriteContext::createFromContext($context ?? Context::createDefaultContext()),
            [
                new DeleteCommand(
                    $definition,
                    ['id' => Uuid::fromHexToBytes($id)],
                    new EntityExistence($entityName, ['id' => $id], true, false, false, []),
                ),
            ],
        );
    }

    private function getDefinitionForEntity(string $entityName): EntityDefinition
    {
        $definitions = [
            MediaDefinition::ENTITY_NAME => MediaDefinition::class,
            MediaThumbnailDefinition::ENTITY_NAME => MediaThumbnailDefinition::class,
            MediaFolderDefinition::ENTITY_NAME => MediaFolderDefinition::class,
        ];

        $class = $definitions[$entityName];

        new StaticDefinitionInstanceRegistry(
            [$definition = new $class()],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class),
        );

        return $definition;
    }
}
