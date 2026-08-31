<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\MediaSerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\MediaSerializerSubscriber;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\FieldSerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(MediaSerializer::class)]
class MediaSerializerTest extends TestCase
{
    public function testExistingMediaWithSameHashDoesNotPersistDownloadedFileAgain(): void
    {
        $context = Context::createDefaultContext();
        $mediaDefinition = new MediaDefinition();
        $mediaDefinition->compile(static::createStub(DefinitionInstanceRegistry::class));

        $mediaService = $this->createMock(MediaService::class);
        $fileSaver = $this->createMock(FileSaver::class);
        $mediaFolderRepository = static::createStub(EntityRepository::class);
        $mediaRepository = $this->createMock(EntityRepository::class);

        $mediaSerializer = new MediaSerializer($mediaService, $fileSaver, $mediaFolderRepository, $mediaRepository);
        $mediaSerializer->setRegistry(new SerializerRegistry([], [new FieldSerializer()]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new MediaSerializerSubscriber($mediaSerializer));

        $existingMediaId = Uuid::randomHex();
        $hash = 'existing-file-hash';

        $mediaService->expects($this->once())
            ->method('fetchFile')
            ->willReturn(new MediaFile(
                '/tmp/foo/bar/baz',
                'image/png',
                'png',
                1337,
                $hash
            ));

        $mediaRepository->expects($this->once())
            ->method('searchIds')
            ->willReturn(IdSearchResult::fromIds([$existingMediaId], new Criteria(), $context));

        $fileSaver->expects($this->never())
            ->method('persistFileToMedia');

        $result = $mediaSerializer->deserialize(new Config([], [], []), $mediaDefinition, [
            'url' => 'http://172.16.11.80/shopware-logo.png',
            'mediaFolderId' => Uuid::randomHex(),
        ]);
        $result = \is_array($result) ? $result : iterator_to_array($result);

        static::assertSame($existingMediaId, $result['id']);

        $writtenResult = new EntityWriteResult($existingMediaId, $result, MediaDefinition::ENTITY_NAME, 'insert');
        $writtenEvent = new EntityWrittenEvent(MediaDefinition::ENTITY_NAME, [$writtenResult], $context);
        $eventDispatcher->dispatch($writtenEvent, 'media.written');
    }

    public function testExistingMediaWithSameIdAndHashDoesNotPersistDownloadedFileAgain(): void
    {
        $context = Context::createDefaultContext();
        $mediaDefinition = new MediaDefinition();
        $mediaDefinition->compile(static::createStub(DefinitionInstanceRegistry::class));

        $mediaService = $this->createMock(MediaService::class);
        $fileSaver = $this->createMock(FileSaver::class);
        $mediaFolderRepository = static::createStub(EntityRepository::class);
        $mediaRepository = $this->createMock(EntityRepository::class);

        $mediaSerializer = new MediaSerializer($mediaService, $fileSaver, $mediaFolderRepository, $mediaRepository);
        $mediaSerializer->setRegistry(new SerializerRegistry([], [new FieldSerializer()]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new MediaSerializerSubscriber($mediaSerializer));

        $existingMediaId = Uuid::randomHex();
        $hash = 'existing-file-hash';

        $mediaEntity = new MediaEntity();
        $mediaEntity->assign([
            'id' => $existingMediaId,
            'url' => 'http://shopware.test/media/generated/path/shopware-logo.png',
            'metaData' => [
                'hash' => $hash,
            ],
        ]);

        $mediaRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(MediaDefinition::ENTITY_NAME, 1, new MediaCollection([$mediaEntity]), null, new Criteria(), $context));

        $mediaService->expects($this->once())
            ->method('fetchFile')
            ->willReturn(new MediaFile(
                '/tmp/foo/bar/baz',
                'image/png',
                'png',
                1337,
                $hash
            ));

        $mediaRepository->expects($this->never())
            ->method('searchIds');

        $fileSaver->expects($this->never())
            ->method('persistFileToMedia');

        $result = $mediaSerializer->deserialize(new Config([], [], []), $mediaDefinition, [
            'id' => $existingMediaId,
            'url' => 'http://shopware.test/media/exported/path/shopware-logo.png',
            'mediaFolderId' => Uuid::randomHex(),
        ]);
        $result = \is_array($result) ? $result : iterator_to_array($result);

        static::assertSame($existingMediaId, $result['id']);

        $writtenResult = new EntityWriteResult($existingMediaId, $result, MediaDefinition::ENTITY_NAME, 'update');
        $writtenEvent = new EntityWrittenEvent(MediaDefinition::ENTITY_NAME, [$writtenResult], $context);
        $eventDispatcher->dispatch($writtenEvent, 'media.written');
    }
}
