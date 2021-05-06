<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @group slow
 * @group skip-paratest
 */
class MediaFolderRepositoryDecoratorTest extends TestCase
{
    use IntegrationTestBehaviour;
    use QueueTestBehaviour;

    private const FIXTURE_FILE = __DIR__ . '/../fixtures/shopware-logo.png';

    /**
     * @var EntityRepositoryInterface
     */
    private $mediaRepository;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var EntityRepositoryInterface
     */
    private $folderRepository;

    protected function setUp(): void
    {
        $this->folderRepository = $this->getContainer()->get('media_folder.repository');
        $this->mediaRepository = $this->getContainer()->get('media.repository');
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
        /** @var EntitySearchResult|null $media */
        $media = null;
        $this->context->scope(Context::USER_SCOPE, function (Context $context) use (&$media, $folderId, $folderRepository): void {
            $media = $folderRepository->search(new Criteria([$folderId]), $context);
        });

        static::assertNotNull($media);
        static::assertEquals(0, $media->count());
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

        static::assertEquals(1, $media->count());
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

        $mediaPath = $this->getContainer()->get(UrlGeneratorInterface::class)->getRelativeMediaUrl($media);

        $this->getPublicFilesystem()->putStream($mediaPath, fopen(self::FIXTURE_FILE, 'rb'));

        $this->folderRepository->delete([['id' => $folderId]], $this->context);

        static::assertEquals(0, $this->folderRepository->search(new Criteria([$folderId]), $this->context)->getTotal());
        static::assertEquals(0, $this->mediaRepository->search(new Criteria([$mediaId]), $this->context)->getTotal());

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

        $childMediaPath = $this->getContainer()->get(UrlGeneratorInterface::class)->getRelativeMediaUrl($media->get($childMediaId));
        $parentMediaPath = $this->getContainer()->get(UrlGeneratorInterface::class)->getRelativeMediaUrl($media->get($parentMediaId));

        $this->getPublicFilesystem()->putStream($childMediaPath, fopen(self::FIXTURE_FILE, 'rb'));
        $this->getPublicFilesystem()->putStream($parentMediaPath, fopen(self::FIXTURE_FILE, 'rb'));

        $this->folderRepository->delete([['id' => $parentFolderId]], $this->context);

        static::assertEquals(0, $this->folderRepository->search(new Criteria([$parentFolderId, $childFolderId]), $this->context)->getTotal());
        static::assertEquals(0, $this->mediaRepository->search(new Criteria([$childMediaId, $parentMediaId]), $this->context)->getTotal());

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

        $childMediaPath = $this->getContainer()->get(UrlGeneratorInterface::class)->getRelativeMediaUrl($media->get($childMediaId));
        $parentMediaPath = $this->getContainer()->get(UrlGeneratorInterface::class)->getRelativeMediaUrl($media->get($parentMediaId));

        $this->getPublicFilesystem()->putStream($childMediaPath, fopen(self::FIXTURE_FILE, 'rb'));
        $this->getPublicFilesystem()->putStream($parentMediaPath, fopen(self::FIXTURE_FILE, 'rb'));

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

        $childMediaPath = $this->getContainer()->get(UrlGeneratorInterface::class)->getRelativeMediaUrl($media->get($childMediaId));
        $parentMediaPath = $this->getContainer()->get(UrlGeneratorInterface::class)->getRelativeMediaUrl($media->get($parentMediaId));

        $this->getPublicFilesystem()->putStream($childMediaPath, fopen(self::FIXTURE_FILE, 'rb'));
        $this->getPublicFilesystem()->putStream($parentMediaPath, fopen(self::FIXTURE_FILE, 'rb'));

        $this->folderRepository->delete([['id' => $parentFolderId], ['id' => $childFolderId]], $this->context);

        static::assertEquals(0, $this->folderRepository->search(new Criteria([$parentFolderId, $childFolderId]), $this->context)->getTotal());
        static::assertEquals(0, $this->mediaRepository->search(new Criteria([$childMediaId, $parentMediaId]), $this->context)->getTotal());

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

        $childMediaPath = $this->getContainer()->get(UrlGeneratorInterface::class)->getRelativeMediaUrl($media->get($childMediaId));
        $parentMediaPath = $this->getContainer()->get(UrlGeneratorInterface::class)->getRelativeMediaUrl($media->get($parentMediaId));

        $this->getPublicFilesystem()->putStream($childMediaPath, fopen(self::FIXTURE_FILE, 'rb'));
        $this->getPublicFilesystem()->putStream($parentMediaPath, fopen(self::FIXTURE_FILE, 'rb'));

        $this->folderRepository->delete([['id' => $childFolderId], ['id' => $parentFolderId]], $this->context);

        static::assertEquals(0, $this->folderRepository->search(new Criteria([$parentFolderId, $childFolderId]), $this->context)->getTotal());
        static::assertEquals(0, $this->mediaRepository->search(new Criteria([$childMediaId, $parentMediaId]), $this->context)->getTotal());

        $this->runWorker();

        static::assertFalse($this->getPublicFilesystem()->has($childMediaPath));
        static::assertFalse($this->getPublicFilesystem()->has($parentMediaPath));
    }
}
