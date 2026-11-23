<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationCollection;
use Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationEntity;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\MediaFolderService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MediaFolderService::class)]
class MediaFolderServiceTest extends TestCase
{
    public function testDissolveFailsWhenFolderDoesNotExist(): void
    {
        $folderRepository = new StaticEntityRepository([new MediaFolderCollection()]);
        $service = $this->createService(
            StaticEntityRepository::of(MediaCollection::class),
            $folderRepository,
            StaticEntityRepository::of(MediaFolderConfigurationCollection::class)
        );

        $this->expectExceptionObject(MediaException::mediaFolderIdNotFound('missing'));

        $service->dissolve('missing', Context::createDefaultContext());
    }

    public function testDissolveMovesMediaAndSubFoldersToParent(): void
    {
        $folder = $this->createFolder('folder', 'parent', useParentConfiguration: true);
        $childA = $this->createFolder('child-a', null, useParentConfiguration: true);
        $childB = $this->createFolder('child-b', null, useParentConfiguration: false);

        $mediaRepository = StaticEntityRepository::of(MediaCollection::class, [['media-1', 'media-2']]);
        $folderRepository = new StaticEntityRepository([
            new MediaFolderCollection([$folder]),
            new MediaFolderCollection([$childA, $childB]),
        ]);
        $service = $this->createService($mediaRepository, $folderRepository, StaticEntityRepository::of(MediaFolderConfigurationCollection::class));

        $service->dissolve('folder', Context::createDefaultContext());

        static::assertSame([
            [
                ['id' => 'media-1', 'mediaFolderId' => 'parent'],
                ['id' => 'media-2', 'mediaFolderId' => 'parent'],
            ]], $mediaRepository->updates);
        static::assertSame([[
            ['id' => 'child-a', 'parentId' => 'parent'],
            ['id' => 'child-b', 'parentId' => 'parent'],
        ]], $folderRepository->updates);
        static::assertSame([[['id' => 'folder']]], $folderRepository->deletes);
    }

    public function testDissolveDeletesOwnConfigurationWhenFolderHasNoSubFolders(): void
    {
        $folder = $this->createFolder('folder', null, useParentConfiguration: false);
        $folder->setConfigurationId('configuration');
        $folderRepository = new StaticEntityRepository([
            new MediaFolderCollection([$folder]),
            new MediaFolderCollection(),
        ]);
        $configurationRepository = StaticEntityRepository::of(MediaFolderConfigurationCollection::class);
        $service = $this->createService(
            StaticEntityRepository::of(MediaCollection::class, [[]]),
            $folderRepository,
            $configurationRepository
        );

        $service->dissolve('folder', Context::createDefaultContext());

        static::assertSame([[['id' => 'configuration']]], $configurationRepository->deletes);
        static::assertSame([[['id' => 'folder']]], $folderRepository->deletes);
    }

    public function testDissolveDeletesOwnConfigurationWhenNoSubFolderInheritsConfiguration(): void
    {
        $folder = $this->createFolder('folder', null, useParentConfiguration: false);
        $folder->setConfigurationId('configuration');
        $child = $this->createFolder('child', null, useParentConfiguration: false);
        $folderRepository = new StaticEntityRepository([
            new MediaFolderCollection([$folder]),
            new MediaFolderCollection([$child]),
        ]);
        $configurationRepository = StaticEntityRepository::of(MediaFolderConfigurationCollection::class);
        $service = $this->createService(
            StaticEntityRepository::of(MediaCollection::class, [[]]),
            $folderRepository,
            $configurationRepository
        );

        $service->dissolve('folder', Context::createDefaultContext());

        static::assertSame([[['id' => 'configuration']]], $configurationRepository->deletes);
        static::assertSame([[
            ['id' => 'child', 'parentId' => null],
        ]], $folderRepository->updates);
    }

    public function testDissolveClonesConfigurationForMultipleInheritingSubFolders(): void
    {
        $folder = $this->createFolder('folder', 'parent', useParentConfiguration: false);
        $folder->setConfigurationId('configuration');
        $configuration = new MediaFolderConfigurationEntity();
        $configuration->setId('child-configuration');
        $childA = $this->createFolder('child-a', null, useParentConfiguration: true);
        $childA->setConfiguration($configuration);
        $childB = $this->createFolder('child-b', null, useParentConfiguration: true);
        $configurationRepository = $this->createMock(EntityRepository::class);
        $configurationRepository->expects($this->once())->method('clone')->with('child-configuration', static::isInstanceOf(Context::class), static::isString())->willReturn(static::createStub(EntityWrittenContainerEvent::class));
        $folderRepository = new StaticEntityRepository([
            new MediaFolderCollection([$folder]),
            new MediaFolderCollection([$childA, $childB]),
        ]);
        $service = $this->createService(
            StaticEntityRepository::of(MediaCollection::class, [[]]),
            $folderRepository,
            $configurationRepository
        );

        $service->dissolve('folder', Context::createDefaultContext());

        $updates = $folderRepository->updates[0];
        static::assertIsArray($updates[0]);
        static::assertIsArray($updates[1]);
        static::assertFalse($updates[0]['useParentConfiguration']);
        static::assertFalse($updates[1]['useParentConfiguration']);
        static::assertIsString($updates[1]['configurationId']);
        static::assertTrue(Uuid::isValid($updates[1]['configurationId']));
    }

    private function createFolder(string $id, ?string $parentId, bool $useParentConfiguration): MediaFolderEntity
    {
        $folder = new MediaFolderEntity();
        $folder->setId($id);
        $folder->setParentId($parentId);
        $folder->setUseParentConfiguration($useParentConfiguration);

        return $folder;
    }

    /**
     * @param EntityRepository<MediaCollection> $mediaRepository
     * @param EntityRepository<MediaFolderCollection> $folderRepository
     * @param EntityRepository<MediaFolderConfigurationCollection> $configurationRepository
     */
    private function createService(
        EntityRepository $mediaRepository,
        EntityRepository $folderRepository,
        EntityRepository $configurationRepository
    ): MediaFolderService {
        return new MediaFolderService($mediaRepository, $folderRepository, $configurationRepository);
    }
}
