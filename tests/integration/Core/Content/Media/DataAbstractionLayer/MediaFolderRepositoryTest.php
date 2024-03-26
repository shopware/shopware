<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Media\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Group('slow')]
#[Group('skip-paratest')]
class MediaFolderRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;
    use QueueTestBehaviour;

    private const FIXTURE_FILE = __DIR__ . '/../fixtures/shopware-logo.png';

    /**
     * @var EntityRepository<MediaCollection>
     */
    private EntityRepository $mediaRepository;

    private Context $context;

    /**
     * @var EntityRepository<MediaFolderCollection>
     */
    private EntityRepository $folderRepository;

    protected function setUp(): void
    {
        $this->folderRepository = static::getContainer()->get('media_folder.repository');
        $this->mediaRepository = static::getContainer()->get('media.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testPrivateFolderNotReadable(): void
    {
        $folderId = Uuid::randomHex();
        $configId = Uuid::randomHex();

        $this->folderRepository->create([
            [
                'id' => $folderId,
                'name' => 'testFolder',
                'configuration' => [
                    'id' => $configId,
                    'private' => true,
                ],
            ],
        ], $this->context);

        $folderRepository = $this->folderRepository;
        $media = null;
        $this->context->scope(Context::USER_SCOPE, function (Context $context) use (&$media, $folderId, $folderRepository): void {
            $media = $folderRepository->search(new Criteria([$folderId]), $context);
        });

        static::assertNotNull($media);
        static::assertCount(0, $media);
    }

    public function testFolderWithoutConfigIsReadable(): void
    {
        $folderId = Uuid::randomHex();
        $configId = Uuid::randomHex();

        $this->folderRepository->create([
            [
                'id' => $folderId,
                'name' => 'testFolder',
                'configurationId' => $configId,
            ],
        ], $this->context);

        $media = $this->folderRepository->search(new Criteria([$folderId]), $this->context);

        static::assertCount(1, $media);
    }

    public function testDeleteFolderAlsoDeletesMedia(): void
    {
        $folderId = Uuid::randomHex();
        $mediaId = Uuid::randomHex();

        $this->folderRepository->create([
            [
                'id' => $folderId,
                'name' => 'testFolder',
                'configuration' => [],
            ],
        ], $this->context);

        $this->mediaRepository->create(
            [
                [
                    'id' => $mediaId,
                    'name' => 'test media',
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'fileName' => $mediaId . '-' . (new \DateTime())->getTimestamp(),
                    'mediaFolderId' => $folderId,
                ],
            ],
            $this->context
        );
        $media = $this->mediaRepository->search(new Criteria([$mediaId]), $this->context)->get($mediaId);
        static::assertInstanceOf(MediaEntity::class, $media);

        $mediaPath = $media->getPath();

        $file = fopen(self::FIXTURE_FILE, 'r');
        static::assertIsResource($file);
        $this->getPublicFilesystem()->writeStream($mediaPath, $file);

        $this->folderRepository->delete([['id' => $folderId]], $this->context);

        static::assertSame(0, $this->folderRepository->search(new Criteria([$folderId]), $this->context)->getTotal());
        static::assertSame(0, $this->mediaRepository->search(new Criteria([$mediaId]), $this->context)->getTotal());

        $this->runWorker();

        static::assertFalse($this->getPublicFilesystem()->has($mediaPath));
    }

    public function testDeleteFolderAlsoDeletesSubFoldersWithMedia(): void
    {
        $childFolderId = Uuid::randomHex();
        $parentFolderId = Uuid::randomHex();
        $childMediaId = Uuid::randomHex();
        $parentMediaId = Uuid::randomHex();

        $this->folderRepository->create([
            [
                'id' => $parentFolderId,
                'name' => 'parent',
                'configuration' => [],
            ],
            [
                'id' => $childFolderId,
                'name' => 'testFolder',
                'configuration' => [],
                'parentId' => $parentFolderId,
            ],
        ], $this->context);

        $this->mediaRepository->create(
            [
                [
                    'id' => $childMediaId,
                    'name' => 'test media',
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'fileName' => $childMediaId . '-' . (new \DateTime())->getTimestamp(),
                    'mediaFolderId' => $childFolderId,
                ],
                [
                    'id' => $parentMediaId,
                    'name' => 'test media',
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'fileName' => $parentMediaId . '-' . (new \DateTime())->getTimestamp(),
                    'mediaFolderId' => $parentFolderId,
                ],
            ],
            $this->context
        );
        $media = $this->mediaRepository->search(new Criteria([$childMediaId, $parentMediaId]), $this->context);

        $childMedia = $media->get($childMediaId);
        static::assertInstanceOf(MediaEntity::class, $childMedia);

        $parentMedia = $media->get($parentMediaId);
        static::assertInstanceOf(MediaEntity::class, $parentMedia);

        $childMediaPath = $childMedia->getPath();
        $parentMediaPath = $parentMedia->getPath();

        $file = fopen(self::FIXTURE_FILE, 'r');
        static::assertIsResource($file);
        $this->getPublicFilesystem()->writeStream($childMediaPath, $file);
        $this->getPublicFilesystem()->writeStream($parentMediaPath, $file);

        $this->folderRepository->delete([['id' => $parentFolderId]], $this->context);

        static::assertSame(0, $this->folderRepository->search(new Criteria([$parentFolderId, $childFolderId]), $this->context)->getTotal());
        static::assertSame(0, $this->mediaRepository->search(new Criteria([$childMediaId, $parentMediaId]), $this->context)->getTotal());

        $this->runWorker();

        static::assertFalse($this->getPublicFilesystem()->has($childMediaPath));
        static::assertFalse($this->getPublicFilesystem()->has($parentMediaPath));
    }

    public function testDeleteFolderDoesNotTouchParent(): void
    {
        $childFolderId = Uuid::randomHex();
        $parentFolderId = Uuid::randomHex();
        $childMediaId = Uuid::randomHex();
        $parentMediaId = Uuid::randomHex();

        $this->folderRepository->create([
            [
                'id' => $parentFolderId,
                'name' => 'parent',
                'configuration' => [],
            ],
            [
                'id' => $childFolderId,
                'name' => 'testFolder',
                'configuration' => [],
                'parentId' => $parentFolderId,
            ],
        ], $this->context);

        $this->mediaRepository->create(
            [
                [
                    'id' => $childMediaId,
                    'name' => 'test media',
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'path' => 'media/test_media_1.png',
                    'fileName' => $childMediaId . '-' . (new \DateTime())->getTimestamp(),
                    'mediaFolderId' => $childFolderId,
                ],
                [
                    'id' => $parentMediaId,
                    'name' => 'test media',
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'path' => 'media/test_media_2.png',
                    'fileName' => $parentMediaId . '-' . (new \DateTime())->getTimestamp(),
                    'mediaFolderId' => $parentFolderId,
                ],
            ],
            $this->context
        );
        $media = $this->mediaRepository->search(new Criteria([$childMediaId, $parentMediaId]), $this->context);

        $childMedia = $media->get($childMediaId);
        static::assertInstanceOf(MediaEntity::class, $childMedia);

        $parentMedia = $media->get($parentMediaId);
        static::assertInstanceOf(MediaEntity::class, $parentMedia);

        $childMediaPath = $childMedia->getPath();
        $parentMediaPath = $parentMedia->getPath();

        $file = fopen(self::FIXTURE_FILE, 'r');
        static::assertIsResource($file);
        $this->getPublicFilesystem()->writeStream($childMediaPath, $file);
        $this->getPublicFilesystem()->writeStream($parentMediaPath, $file);

        $this->folderRepository->delete([['id' => $childFolderId]], $this->context);

        static::assertArrayHasKey($parentFolderId, $this->folderRepository->search(new Criteria([$parentFolderId, $childFolderId]), $this->context)->getIds());
        static::assertArrayHasKey($parentMediaId, $this->mediaRepository->search(new Criteria([$childMediaId, $parentMediaId]), $this->context)->getIds());

        $this->runWorker();

        static::assertFalse($this->getPublicFilesystem()->has($childMediaPath));
        static::assertTrue($this->getPublicFilesystem()->has($parentMediaPath));
    }

    public function testDeleteFolderParentAndChild(): void
    {
        $childFolderId = Uuid::randomHex();
        $parentFolderId = Uuid::randomHex();
        $childMediaId = Uuid::randomHex();
        $parentMediaId = Uuid::randomHex();

        $this->folderRepository->create([
            [
                'id' => $parentFolderId,
                'name' => 'parent',
                'configuration' => [],
            ],
            [
                'id' => $childFolderId,
                'name' => 'testFolder',
                'configuration' => [],
                'parentId' => $parentFolderId,
            ],
        ], $this->context);

        $this->mediaRepository->create(
            [
                [
                    'id' => $childMediaId,
                    'name' => 'test media',
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'fileName' => $childMediaId . '-' . (new \DateTime())->getTimestamp(),
                    'mediaFolderId' => $childFolderId,
                ],
                [
                    'id' => $parentMediaId,
                    'name' => 'test media',
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'fileName' => $parentMediaId . '-' . (new \DateTime())->getTimestamp(),
                    'mediaFolderId' => $parentFolderId,
                ],
            ],
            $this->context
        );
        $media = $this->mediaRepository->search(new Criteria([$childMediaId, $parentMediaId]), $this->context);

        $childMedia = $media->get($childMediaId);
        static::assertInstanceOf(MediaEntity::class, $childMedia);

        $parentMedia = $media->get($parentMediaId);
        static::assertInstanceOf(MediaEntity::class, $parentMedia);

        $childMediaPath = $childMedia->getPath();
        $parentMediaPath = $parentMedia->getPath();

        $file = fopen(self::FIXTURE_FILE, 'r');
        static::assertIsResource($file);
        $this->getPublicFilesystem()->writeStream($childMediaPath, $file);
        $this->getPublicFilesystem()->writeStream($parentMediaPath, $file);

        $this->folderRepository->delete([['id' => $parentFolderId], ['id' => $childFolderId]], $this->context);

        static::assertSame(0, $this->folderRepository->search(new Criteria([$parentFolderId, $childFolderId]), $this->context)->getTotal());
        static::assertSame(0, $this->mediaRepository->search(new Criteria([$childMediaId, $parentMediaId]), $this->context)->getTotal());

        $this->runWorker();

        static::assertFalse($this->getPublicFilesystem()->has($childMediaPath));
        static::assertFalse($this->getPublicFilesystem()->has($parentMediaPath));
    }

    public function testDeleteFolderChildAndParent(): void
    {
        $childFolderId = Uuid::randomHex();
        $parentFolderId = Uuid::randomHex();
        $childMediaId = Uuid::randomHex();
        $parentMediaId = Uuid::randomHex();

        $this->folderRepository->create([
            [
                'id' => $parentFolderId,
                'name' => 'parent',
                'configuration' => [],
            ],
            [
                'id' => $childFolderId,
                'name' => 'testFolder',
                'configuration' => [],
                'parentId' => $parentFolderId,
            ],
        ], $this->context);

        $this->mediaRepository->create(
            [
                [
                    'id' => $childMediaId,
                    'name' => 'test media',
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'fileName' => $childMediaId . '-' . (new \DateTime())->getTimestamp(),
                    'mediaFolderId' => $childFolderId,
                ],
                [
                    'id' => $parentMediaId,
                    'name' => 'test media',
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'fileName' => $parentMediaId . '-' . (new \DateTime())->getTimestamp(),
                    'mediaFolderId' => $parentFolderId,
                ],
            ],
            $this->context
        );
        $media = $this->mediaRepository->search(new Criteria([$childMediaId, $parentMediaId]), $this->context);

        $childMedia = $media->get($childMediaId);
        static::assertInstanceOf(MediaEntity::class, $childMedia);

        $parentMedia = $media->get($parentMediaId);
        static::assertInstanceOf(MediaEntity::class, $parentMedia);

        $childMediaPath = $childMedia->getPath();
        $parentMediaPath = $parentMedia->getPath();

        $file = fopen(self::FIXTURE_FILE, 'r');
        static::assertIsResource($file);
        $this->getPublicFilesystem()->writeStream($childMediaPath, $file);
        $this->getPublicFilesystem()->writeStream($parentMediaPath, $file);

        $this->folderRepository->delete([['id' => $childFolderId], ['id' => $parentFolderId]], $this->context);

        static::assertSame(0, $this->folderRepository->search(new Criteria([$parentFolderId, $childFolderId]), $this->context)->getTotal());
        static::assertSame(0, $this->mediaRepository->search(new Criteria([$childMediaId, $parentMediaId]), $this->context)->getTotal());

        $this->runWorker();

        static::assertFalse($this->getPublicFilesystem()->has($childMediaPath));
        static::assertFalse($this->getPublicFilesystem()->has($parentMediaPath));
    }
}
