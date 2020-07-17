<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport\DataAbstractionLayer\Serializer\Entity;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\MediaSerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcher;

class MediaSerializerTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testDeserializeDownloadsAndPersistsMedia(): void
    {
        $context = Context::createDefaultContext();
        $serializerRegistry = $this->getContainer()->get(SerializerRegistry::class);
        $mediaDefinition = $this->getContainer()->get(MediaDefinition::class);

        $mediaService = $this->createMock(MediaService::class);
        $fileSaver = $this->createMock(FileSaver::class);

        $mediaFolderRepository = $this->createMock(EntityRepositoryInterface::class);
        $mediaRepository = $this->createMock(EntityRepositoryInterface::class);

        $mediaSerializer = new MediaSerializer($mediaService, $fileSaver, $mediaFolderRepository, $mediaRepository);
        $mediaSerializer->setRegistry($serializerRegistry);

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber($mediaSerializer);

        $mediaId = Uuid::randomHex();
        $expectedDestination = 'shopware-logo';
        $record = [
            'id' => $mediaId,
            'title' => 'Logo',
            'url' => 'http://172.16.11.80/shopware-logo.png',
            'mediaFolderId' => Uuid::randomHex(),
        ];

        $expectedMediaFile = new MediaFile(
            '/tmp/foo/bar/baz',
            'image/png',
            'png',
            1337
        );
        $mediaService->expects(static::once())
            ->method('fetchFile')
            ->willReturn($expectedMediaFile);

        $fileSaver->expects(static::once())
            ->method('persistFileToMedia')
            ->willReturnCallback(function (MediaFile $m, string $dest, string $id) use ($expectedMediaFile, $expectedDestination, $mediaId): void {
                $this->assertSame($expectedMediaFile, $m);
                $this->assertSame($expectedDestination, $dest);
                $this->assertSame($mediaId, $id);
            });

        $result = $mediaSerializer->deserialize(new Config([], []), $mediaDefinition, $record);

        $writtenResult = new EntityWriteResult($mediaId, $result, 'media', 'insert');
        $writtenEvent = new EntityWrittenEvent('media', [$writtenResult], $context);
        $eventDispatcher->dispatch($writtenEvent, 'media.written');
    }

    public function testExistingMediaWithSameUrlDoesNotDownload(): void
    {
        $context = Context::createDefaultContext();
        $serializerRegistry = $this->getContainer()->get(SerializerRegistry::class);
        $mediaDefinition = $this->getContainer()->get(MediaDefinition::class);

        $mediaService = $this->createMock(MediaService::class);
        $fileSaver = $this->createMock(FileSaver::class);

        $mediaFolderRepository = $this->createMock(EntityRepositoryInterface::class);
        $mediaRepository = $this->createMock(EntityRepositoryInterface::class);

        $mediaSerializer = new MediaSerializer($mediaService, $fileSaver, $mediaFolderRepository, $mediaRepository);
        $mediaSerializer->setRegistry($serializerRegistry);

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber($mediaSerializer);

        $mediaId = Uuid::randomHex();
        $record = [
            'id' => $mediaId,
            'url' => 'http://172.16.11.80/shopware-logo.png',
        ];

        $mediaEntity = new MediaEntity();
        $mediaEntity->assign($record);

        $record['mediaFolderId'] = Uuid::randomHex();
        $record['translations'] = [
            Defaults::LANGUAGE_SYSTEM => [
                'title' => 'Logo',
                'alt' => 'Logo description',
            ],
        ];

        $mediaService->expects(static::never())
            ->method('fetchFile');

        $fileSaver->expects(static::never())
            ->method('persistFileToMedia');

        $searchResult = new EntitySearchResult(1, new MediaCollection([$mediaEntity]), null, new Criteria(), $context);
        $mediaRepository->method('search')->willReturn($searchResult);

        $result = $mediaSerializer->deserialize(new Config([], []), $mediaDefinition, $record);

        static::assertArrayNotHasKey('url', $result);

        $expected = $record;
        unset($expected['url']);

        // other properties are written
        static::assertEquals($expected, $result);

        $writtenResult = new EntityWriteResult($mediaId, $result, 'media', 'insert');
        $writtenEvent = new EntityWrittenEvent('media', [$writtenResult], $context);
        $eventDispatcher->dispatch($writtenEvent, 'media.written');
    }

    public function testOnlyUrl(): void
    {
        $context = Context::createDefaultContext();
        $serializerRegistry = $this->getContainer()->get(SerializerRegistry::class);
        $mediaDefinition = $this->getContainer()->get(MediaDefinition::class);

        $mediaService = $this->createMock(MediaService::class);
        $fileSaver = $this->createMock(FileSaver::class);

        $mediaFolderRepository = $this->getContainer()->get('media_folder.repository');
        $mediaRepository = $this->createMock(EntityRepositoryInterface::class);

        $mediaSerializer = new MediaSerializer($mediaService, $fileSaver, $mediaFolderRepository, $mediaRepository);
        $mediaSerializer->setRegistry($serializerRegistry);

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber($mediaSerializer);

        $expectedDestination = 'shopware-logo';
        $record = [
            'url' => 'http://172.16.11.80/shopware-logo.png',
        ];

        $expectedMediaFile = new MediaFile(
            '/tmp/foo/bar/baz',
            'image/png',
            'png',
            1337
        );
        $mediaService->expects(static::once())
            ->method('fetchFile')
            ->willReturn($expectedMediaFile);

        $fileSaver->expects(static::once())
            ->method('persistFileToMedia')
            ->willReturnCallback(function (MediaFile $m, string $dest) use ($expectedMediaFile, $expectedDestination): void {
                $this->assertSame($expectedMediaFile, $m);
                $this->assertSame($expectedDestination, $dest);
            });
        $config = new Config([], []);

        $result = $mediaSerializer->deserialize($config, $mediaDefinition, $record);

        $writtenResult = new EntityWriteResult($result['id'], $result, 'media', 'insert');
        $writtenEvent = new EntityWrittenEvent('media', [$writtenResult], $context);
        $eventDispatcher->dispatch($writtenEvent, 'media.written');
    }

    public function testEmpty(): void
    {
        $serializerRegistry = $this->getContainer()->get(SerializerRegistry::class);
        $mediaDefinition = $this->getContainer()->get(MediaDefinition::class);

        $mediaService = $this->createMock(MediaService::class);
        $fileSaver = $this->createMock(FileSaver::class);

        $mediaFolderRepository = $this->getContainer()->get('media_folder.repository');
        $mediaRepository = $this->createMock(EntityRepositoryInterface::class);

        $mediaSerializer = new MediaSerializer($mediaService, $fileSaver, $mediaFolderRepository, $mediaRepository);
        $mediaSerializer->setRegistry($serializerRegistry);
        $config = new Config([], []);

        $actual = $mediaSerializer->deserialize($config, $mediaDefinition, []);
        static::assertEmpty($actual);
    }
}
