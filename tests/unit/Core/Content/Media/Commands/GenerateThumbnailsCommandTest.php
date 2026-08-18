<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Commands;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Shopware\Core\Content\Media\Commands\GenerateThumbnailsCommand;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\Message\UpdateThumbnailsMessage;
use Shopware\Core\Content\Media\Thumbnail\ThumbnailService;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Framework\DataAbstractionLayer\TestEntityDefinition;
use Shopware\Core\Test\Stub\MessageBus\CollectingMessageBus;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(GenerateThumbnailsCommand::class)]
class GenerateThumbnailsCommandTest extends TestCase
{
    #[TestDox('Thumbnails are generated synchronously and the result is summarized')]
    public function testGeneratesThumbnailsSynchronously(): void
    {
        $media = $this->createMediaEntity();

        $mediaRepository = new StaticEntityRepository([
            [$media->getId()],
            new MediaCollection([$media]),
            new MediaCollection(),
        ], new TestEntityDefinition());

        $thumbnailService = $this->createMock(ThumbnailService::class);
        $thumbnailService
            ->expects($this->once())
            ->method('updateThumbnails')
            ->willReturn(2);

        $commandTester = new CommandTester(new GenerateThumbnailsCommand(
            $thumbnailService,
            $mediaRepository,
            $this->createFolderRepository(),
            new CollectingMessageBus()
        ));

        static::assertSame(Command::SUCCESS, $commandTester->execute([]));

        $display = $commandTester->getDisplay();
        static::assertStringContainsString('Generating Thumbnails for 1 files.', $display);
        static::assertStringContainsString('Generated', $display);
    }

    #[TestDox('The async option queues one batch job per media batch instead of generating directly')]
    public function testDispatchesBatchJobsAsynchronously(): void
    {
        $media = $this->createMediaEntity();

        $mediaRepository = new StaticEntityRepository([
            new MediaCollection([$media]),
            new MediaCollection(),
        ], new TestEntityDefinition());

        $thumbnailService = $this->createMock(ThumbnailService::class);
        $thumbnailService->expects($this->never())->method('updateThumbnails');

        $messageBus = new CollectingMessageBus();

        $commandTester = new CommandTester(new GenerateThumbnailsCommand(
            $thumbnailService,
            $mediaRepository,
            $this->createFolderRepository(),
            $messageBus
        ));

        static::assertSame(Command::SUCCESS, $commandTester->execute([
            '--async' => true,
            '--strict' => true,
        ]));
        static::assertStringContainsString('Generated 1 Batch jobs!', $commandTester->getDisplay());

        $messages = $messageBus->getMessages();
        static::assertCount(1, $messages);
        $message = $messages[0]->getMessage();
        static::assertInstanceOf(UpdateThumbnailsMessage::class, $message);
        static::assertSame([$media->getId()], array_values($message->getMediaIds()));
        static::assertTrue($message->isStrict());
    }

    #[TestDox('The force option is forwarded to the thumbnail service')]
    public function testForwardsForceOptionSynchronously(): void
    {
        $media = $this->createMediaEntity();

        $mediaRepository = new StaticEntityRepository([
            [$media->getId()],
            new MediaCollection([$media]),
            new MediaCollection(),
        ], new TestEntityDefinition());

        $thumbnailService = $this->createMock(ThumbnailService::class);
        $thumbnailService
            ->expects($this->once())
            ->method('updateThumbnails')
            ->with($media, static::anything(), false, true)
            ->willReturn(1);

        $commandTester = new CommandTester(new GenerateThumbnailsCommand(
            $thumbnailService,
            $mediaRepository,
            $this->createFolderRepository(),
            new CollectingMessageBus()
        ));

        static::assertSame(Command::SUCCESS, $commandTester->execute(['--force' => true]));
    }

    #[TestDox('The force option is forwarded to queued batch jobs')]
    public function testForwardsForceOptionToBatchJobs(): void
    {
        $media = $this->createMediaEntity();

        $mediaRepository = new StaticEntityRepository([
            new MediaCollection([$media]),
            new MediaCollection(),
        ], new TestEntityDefinition());

        $messageBus = new CollectingMessageBus();

        $commandTester = new CommandTester(new GenerateThumbnailsCommand(
            static::createStub(ThumbnailService::class),
            $mediaRepository,
            $this->createFolderRepository(),
            $messageBus
        ));

        static::assertSame(Command::SUCCESS, $commandTester->execute([
            '--async' => true,
            '--force' => true,
        ]));

        $messages = $messageBus->getMessages();
        static::assertCount(1, $messages);
        $message = $messages[0]->getMessage();
        static::assertInstanceOf(UpdateThumbnailsMessage::class, $message);
        static::assertTrue($message->isForce());
        static::assertFalse($message->isStrict());
    }

    #[TestDox('Thumbnail generation is skipped when remote thumbnails are enabled')]
    public function testFailsWhenRemoteThumbnailsAreEnabled(): void
    {
        $commandTester = new CommandTester(new GenerateThumbnailsCommand(
            static::createStub(ThumbnailService::class),
            $this->createMediaRepository(),
            $this->createFolderRepository(),
            new CollectingMessageBus(),
            true
        ));

        static::assertSame(Command::FAILURE, $commandTester->execute([]));
        static::assertStringContainsString('Remote thumbnails are enabled. Skipping thumbnail generation.', $commandTester->getDisplay());
    }

    #[TestDox('A non-numeric batch size is rejected')]
    public function testThrowsOnInvalidBatchSize(): void
    {
        $commandTester = new CommandTester(new GenerateThumbnailsCommand(
            static::createStub(ThumbnailService::class),
            $this->createMediaRepository(),
            $this->createFolderRepository(),
            new CollectingMessageBus()
        ));

        $this->expectExceptionObject(MediaException::invalidBatchSize());

        $commandTester->execute(['--batch-size' => 'not-a-number']);
    }

    #[TestDox('An unknown folder name is rejected')]
    public function testThrowsOnUnknownFolderName(): void
    {
        $commandTester = new CommandTester(new GenerateThumbnailsCommand(
            static::createStub(ThumbnailService::class),
            $this->createMediaRepository(),
            $this->createFolderRepository(),
            new CollectingMessageBus()
        ));

        $this->expectExceptionObject(MediaException::mediaFolderNameNotFound('Products'));

        $commandTester->execute(['--folder-name' => 'Products']);
    }

    private function createMediaEntity(): MediaEntity
    {
        $media = new MediaEntity();
        $media->setId(Uuid::randomHex());
        $media->setFileName('test');
        $media->setMimeType('image/png');
        $media->setFileExtension('png');

        return $media;
    }

    /**
     * @return StaticEntityRepository<MediaCollection>
     */
    private function createMediaRepository(): StaticEntityRepository
    {
        $repository = new StaticEntityRepository([new MediaCollection()], new TestEntityDefinition());

        return $repository;
    }

    /**
     * @return StaticEntityRepository<MediaFolderCollection>
     */
    private function createFolderRepository(): StaticEntityRepository
    {
        $repository = new StaticEntityRepository([new MediaFolderCollection()], new TestEntityDefinition());

        return $repository;
    }
}
