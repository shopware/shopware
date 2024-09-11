<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Media\Thumbnail;

use League\Flysystem\UnableToReadFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationEntity;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeEntity;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\MediaType\DocumentType;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Content\Media\MediaType\MediaType;
use Shopware\Core\Content\Media\Subscriber\MediaDeletionSubscriber;
use Shopware\Core\Content\Media\Thumbnail\ThumbnailService;
use Shopware\Core\Content\Test\Media\MediaFixtures;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Group('slow')]
#[CoversClass(ThumbnailService::class)]
class ThumbnailServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use MediaFixtures;
    use QueueTestBehaviour;

    private Context $context;

    private ThumbnailService $thumbnailService;

    private EntityRepository $mediaRepository;

    private EntityRepository $thumbnailRepository;

    private bool $remoteThumbnailsEnable = false;

    protected function setUp(): void
    {
        $this->mediaRepository = $this->getContainer()->get('media.repository');
        $this->thumbnailRepository = $this->getContainer()->get('media_thumbnail.repository');
        $this->context = Context::createDefaultContext();
        $this->remoteThumbnailsEnable = $this->getContainer()->getParameter('shopware.media.remote_thumbnails.enable');

        $this->thumbnailService = $this->getContainer()->get(ThumbnailService::class);
    }

    public function testThumbnailGeneration(): void
    {
        if ($this->remoteThumbnailsEnable) {
            static::markTestSkipped('Remote thumbnails is enabled. Skipping thumbnail generation test.');
        }

        $this->setFixtureContext($this->context);
        $media = $this->getPngWithFolder();

        $filePath = $media->getPath();
        $resource = fopen(__DIR__ . '/../fixtures/shopware-logo.png', 'r');

        \assert($resource !== false);
        $this->getPublicFilesystem()->writeStream($filePath, $resource);

        $this->thumbnailService->updateThumbnails(
            $media,
            $this->context,
            false
        );

        $this->runWorker();

        $searchCriteria = new Criteria();
        $searchCriteria->setLimit(1);
        $searchCriteria->addFilter(new EqualsFilter('media.id', $media->getId()));
        $searchCriteria->addAssociation('mediaFolder.configuration.mediaThumbnailSizes');

        $mediaResult = $this->mediaRepository->search($searchCriteria, $this->context);

        /** @var MediaEntity $updatedMedia */
        $updatedMedia = $mediaResult->getEntities()->first();

        $thumbnails = $updatedMedia->getThumbnails();
        static::assertInstanceOf(MediaThumbnailCollection::class, $thumbnails);
        static::assertEquals(2, $thumbnails->count());

        foreach ($thumbnails as $thumbnail) {
            $thumbnailPath = $thumbnail->getPath();

            $folder = $updatedMedia->getMediaFolder();
            static::assertInstanceOf(MediaFolderEntity::class, $folder);
            static::assertInstanceOf(MediaFolderConfigurationEntity::class, $folder->getConfiguration());

            $sizes = $folder->getConfiguration()->getMediaThumbnailSizes();
            static::assertInstanceOf(MediaThumbnailSizeCollection::class, $sizes);

            $filtered = $sizes->filter(
                fn (MediaThumbnailSizeEntity $size) => $size->getWidth() === $thumbnail->getWidth() && $size->getHeight() === $thumbnail->getHeight()
            );

            static::assertCount(1, $filtered);
            static::assertTrue($this->getPublicFilesystem()->has($thumbnailPath));
        }
    }

    public function testGeneratorThrowsExceptionIfFileDoesNotExist(): void
    {
        if ($this->remoteThumbnailsEnable) {
            static::markTestSkipped('Remote thumbnails is enabled. Skipping thumbnail generation test.');
        }

        $this->setFixtureContext($this->context);
        $media = $this->getPngWithFolder();

        $this->expectException(UnableToReadFile::class);
        $this->thumbnailService->updateThumbnails(
            $media,
            $this->context,
            false
        );
    }

    public function testGeneratorThrowsExceptionIfFileIsNoImage(): void
    {
        if ($this->remoteThumbnailsEnable) {
            static::markTestSkipped('Remote thumbnails is enabled. Skipping thumbnail generation test.');
        }

        $this->setFixtureContext($this->context);
        $media = $this->getPngWithFolder();

        $filePath = $media->getPath();

        $this->getPublicFilesystem()->write($filePath, 'this is the content of the file, which is not a image');

        $this->expectException(MediaException::class);
        $this->expectExceptionMessage(MediaException::thumbnailNotSupported($media->getId())->getMessage());
        $this->thumbnailService->updateThumbnails(
            $media,
            $this->context,
            false
        );
    }

    public function testItUsesOriginalImageIfItsSmallerThanGeneratedThumbnail(): void
    {
        if ($this->remoteThumbnailsEnable) {
            static::markTestSkipped('Remote thumbnails is enabled. Skipping thumbnail generation test.');
        }

        $this->setFixtureContext($this->context);
        $media = $this->getJpgWithFolder();

        $criteria = new Criteria([$media->getId()]);
        $criteria->addAssociation('mediaFolder.configuration.mediaThumbnailSizes');
        /** @var MediaEntity $media */
        $media = $this->mediaRepository->search($criteria, $this->context)->get($media->getId());

        static::assertInstanceOf(MediaFolderEntity::class, $media->getMediaFolder());
        static::assertInstanceOf(MediaFolderConfigurationEntity::class, $media->getMediaFolder()->getConfiguration());

        $media->getMediaFolder()->getConfiguration()->setMediaThumbnailSizes(
            new MediaThumbnailSizeCollection([
                (new MediaThumbnailSizeEntity())->assign([
                    'id' => Uuid::randomHex(),
                    'width' => 1530,
                    'height' => 1530,
                ]),
            ])
        );
        $media->getMediaFolder()->getConfiguration()->setThumbnailQuality(100);

        $filePath = $media->getPath();
        $resource = fopen(__DIR__ . '/../fixtures/shopware_optimized.jpg', 'r');
        \assert($resource !== false);
        $this->getPublicFilesystem()->writeStream($filePath, $resource);

        $this->thumbnailService->updateThumbnails(
            $media,
            $this->context,
            false
        );

        $this->runWorker();

        /** @var MediaEntity $updatedMedia */
        $updatedMedia = $this->mediaRepository->search(new Criteria([$media->getId()]), $this->context)->get($media->getId());

        $thumbnails = $updatedMedia->getThumbnails();
        static::assertInstanceOf(MediaThumbnailCollection::class, $thumbnails);
        static::assertEquals(1, $thumbnails->count());

        $thumbnail = $thumbnails->first();
        static::assertInstanceOf(MediaThumbnailEntity::class, $thumbnail);

        $thumbnailPath = $thumbnail->getPath();

        static::assertTrue($this->getPublicFilesystem()->has($thumbnailPath));

        $originalSize = $this->getPublicFilesystem()->fileSize($filePath);
        $thumbnailSize = $this->getPublicFilesystem()->fileSize($thumbnailPath);
        static::assertLessThanOrEqual($originalSize, $thumbnailSize);
    }

    public function testItUsesFolderConfigGenerateThumbnails(): void
    {
        if ($this->remoteThumbnailsEnable) {
            static::markTestSkipped('Remote thumbnails is enabled. Skipping thumbnail generation test.');
        }

        $this->setFixtureContext($this->context);
        $media = $this->getJpgWithFolderWithoutThumbnails();

        $filePath = $media->getPath();
        $resource = fopen(__DIR__ . '/../fixtures/shopware.jpg', 'r');
        static::assertNotFalse($resource);

        $this->getPublicFilesystem()->writeStream($filePath, $resource);

        $this->thumbnailService->updateThumbnails($media, $this->context, false);

        /** @var MediaEntity $updatedMedia */
        $updatedMedia = $this->mediaRepository->search(new Criteria([$media->getId()]), $this->context)->get($media->getId());

        $thumbnails = $updatedMedia->getThumbnails();
        static::assertInstanceOf(MediaThumbnailCollection::class, $thumbnails);
        static::assertEquals(0, $thumbnails->count());
    }

    public function testDeleteThumbnailsWithSavedThumbnails(): void
    {
        if ($this->remoteThumbnailsEnable) {
            static::markTestSkipped('Remote thumbnails is enabled. Skipping thumbnail generation test.');
        }

        $mediaId = Uuid::randomHex();
        $mediaExtension = 'png';

        $this->mediaRepository->create(
            [
                [
                    'id' => $mediaId,
                    'name' => 'media without thumbnails',
                    'fileExtension' => $mediaExtension,
                    'mimeType' => 'image/png',
                    'path' => 'foo/media_without_thumbnails.png',
                    'fileName' => $mediaId . '-' . (new \DateTime())->getTimestamp(),
                    'thumbnails' => [
                        [
                            'width' => 100,
                            'height' => 100,
                            'path' => 'foo/thumb_100x100.png',
                            'highDpi' => false,
                        ],
                        [
                            'width' => 300,
                            'height' => 300,
                            'path' => 'foo/thumb_300x300.png',
                            'highDpi' => true,
                        ],
                    ],
                ],
            ],
            $this->context
        );

        /** @var MediaEntity $media */
        $media = $this->mediaRepository->search(new Criteria([$mediaId]), $this->context)->get($mediaId);

        $mediaUrl = $media->getPath();

        static::assertInstanceOf(MediaThumbnailCollection::class, $media->getThumbnails());
        static::assertCount(2, $media->getThumbnails());

        $this->getPublicFilesystem()->write($mediaUrl, 'test content');

        $thumbnailUrls = [];
        foreach ($media->getThumbnails() as $thumbnail) {
            $thumbnailUrl = $thumbnail->getPath();
            $this->getPublicFilesystem()->write($thumbnailUrl, 'test content');
            $thumbnailUrls[] = $thumbnailUrl;
        }

        $this->thumbnailService->deleteThumbnails($media, $this->context);

        $this->runWorker();

        // refresh entity
        $media = $this->mediaRepository->search(new Criteria([$mediaId]), $this->context)->get($mediaId);

        static::assertInstanceOf(MediaEntity::class, $media);
        static::assertSame(0, $media->getThumbnails()?->count());
        static::assertTrue($this->getPublicFilesystem()->has($mediaUrl));
        foreach ($thumbnailUrls as $thumbnailUrl) {
            static::assertFalse($this->getPublicFilesystem()->has($thumbnailUrl));
        }
    }

    public function testThumbnailGenerationThrowsExceptionIfFileTypeIsNotImage(): void
    {
        if ($this->remoteThumbnailsEnable) {
            static::markTestSkipped('Remote thumbnails is enabled. Skipping thumbnail generation test.');
        }

        $this->setFixtureContext($this->context);
        $media = $this->getPng();
        $media->setMediaType(new DocumentType());

        static::assertEquals(0, $this->thumbnailService->updateThumbnails(
            $media,
            $this->context,
            false
        ));
    }

    public function testThumbnailGenerationThrowsExceptionIfFileIsVectorGraphic(): void
    {
        if ($this->remoteThumbnailsEnable) {
            static::markTestSkipped('Remote thumbnails is enabled. Skipping thumbnail generation test.');
        }

        $this->setFixtureContext($this->context);
        $media = $this->getPng();
        static::assertInstanceOf(MediaType::class, $media->getMediaType());
        $media->getMediaType()->addFlag(ImageType::VECTOR_GRAPHIC);

        static::assertEquals(0, $this->thumbnailService->updateThumbnails($media, $this->context, false));
    }

    public function testThumbnailGenerationThrowsExceptionIfFileIsAnimated(): void
    {
        if ($this->remoteThumbnailsEnable) {
            static::markTestSkipped('Remote thumbnails is enabled. Skipping thumbnail generation test.');
        }

        $this->setFixtureContext($this->context);
        $media = $this->getPng();
        static::assertInstanceOf(MediaType::class, $media->getMediaType());
        $media->getMediaType()->addFlag(ImageType::ANIMATED);

        static::assertEquals(0, $this->thumbnailService->updateThumbnails($media, $this->context, false));
    }

    public function testGenerateThumbnails(): void
    {
        if ($this->remoteThumbnailsEnable) {
            static::markTestSkipped('Remote thumbnails is enabled. Skipping thumbnail generation test.');
        }

        $this->setFixtureContext($this->context);
        $media = $this->getPngWithFolder();

        $this->thumbnailRepository->create([
            [
                'mediaId' => $media->getId(),
                'width' => 987,
                'height' => 987,
            ],
            [
                'mediaId' => $media->getId(),
                'width' => 150,
                'height' => 150,
            ],
        ], $this->context);

        $criteria = new Criteria([$media->getId()]);
        $criteria->addAssociation('thumbnails');
        $criteria->addAssociation('mediaFolder.configuration.mediaThumbnailSizes');

        $media = $this->mediaRepository->search($criteria, $this->context)->get($media->getId());
        static::assertInstanceOf(MediaEntity::class, $media);

        $resource = fopen(__DIR__ . '/../fixtures/shopware-logo.png', 'r');
        \assert($resource !== false);

        $url = $media->getPath();

        $this->getPublicFilesystem()->writeStream($url, $resource);

        $this->thumbnailService->generate(new MediaCollection([$media]), $this->context);

        $criteria = new Criteria([$media->getId()]);
        $criteria->addAssociation('thumbnails');

        $media = $this->mediaRepository
            ->search($criteria, $this->context)
            ->get($media->getId());

        static::assertInstanceOf(MediaEntity::class, $media);

        $thumbnails = $media->getThumbnails();

        static::assertInstanceOf(MediaThumbnailCollection::class, $thumbnails);
        static::assertEquals(2, $thumbnails->count());

        $filteredThumbnails = $thumbnails->filter(fn (MediaThumbnailEntity $thumbnail) => ($thumbnail->getWidth() === 300 && $thumbnail->getHeight() === 300)
            || ($thumbnail->getWidth() === 150 && $thumbnail->getHeight() === 150));

        static::assertEquals(2, $filteredThumbnails->count());

        /** @var MediaThumbnailEntity $thumbnail */
        foreach ($filteredThumbnails as $thumbnail) {
            static::assertTrue(
                $this->getPublicFilesystem()->has($thumbnail->getPath()),
                'Thumbnail: ' . $thumbnail->getPath() . ' does not exist'
            );
        }
    }

    public function testGenerateThumbnailsWithSmallerImageSizeThanThumbnailSize(): void
    {
        if ($this->remoteThumbnailsEnable) {
            static::markTestSkipped('Remote thumbnails is enabled. Skipping thumbnail generation test.');
        }

        $this->setFixtureContext($this->context);
        $media = $this->getPngWithFolderHugeThumbnails();

        $this->thumbnailRepository->create([
            [
                'mediaId' => $media->getId(),
                'width' => 150,
                'height' => 150,
            ],
            [
                'mediaId' => $media->getId(),
                'width' => 300,
                'height' => 300,
            ],
        ], $this->context);

        $criteria = new Criteria([$media->getId()]);
        $criteria->addAssociation('thumbnails');
        $criteria->addAssociation('mediaFolder.configuration.mediaThumbnailSizes');

        $media = $this->mediaRepository->search($criteria, $this->context)->get($media->getId());

        static::assertInstanceOf(MediaEntity::class, $media);

        $resource = fopen(__DIR__ . '/../fixtures/shopware-logo.png', 'r');
        \assert($resource !== false);

        $url = $media->getPath();
        $this->getPublicFilesystem()->writeStream($url, $resource);

        $this->thumbnailService->generate(new MediaCollection([$media]), $this->context);

        $criteria = new Criteria([$media->getId()]);
        $criteria->addAssociation('thumbnails');

        $media = $this->mediaRepository
            ->search($criteria, $this->context)
            ->get($media->getId());

        static::assertInstanceOf(MediaEntity::class, $media);

        $thumbnails = $media->getThumbnails();

        static::assertInstanceOf(MediaThumbnailCollection::class, $thumbnails);
        static::assertEquals(2, $thumbnails->count());

        foreach ($thumbnails as $thumbnail) {
            $path = $thumbnail->getPath();
            static::assertTrue(
                $this->getPublicFilesystem()->has($path),
                'Thumbnail: ' . $path . ' does not exist'
            );

            $fileContents = $this->getPublicFilesystem()->read($path);
            $result = getimagesizefromstring($fileContents);

            static::assertIsArray($result);
            static::assertSame(499, $result[0]);
            static::assertSame(266, $result[1]);
        }
    }

    public function testGenerateThumbnailsWithSkipDeleteMessage(): void
    {
        if ($this->remoteThumbnailsEnable) {
            static::markTestSkipped('Remote thumbnails is enabled. Skipping thumbnail generation test.');
        }

        $this->setFixtureContext($this->context);
        $media = $this->getPngWithFolder();

        $this->thumbnailRepository->create([
            [
                'mediaId' => $media->getId(),
                'width' => 987,
                'height' => 987,
            ],
            [
                'mediaId' => $media->getId(),
                'width' => 150,
                'height' => 150,
            ],
        ], $this->context);

        $criteria = new Criteria([$media->getId()]);
        $criteria->addAssociation('thumbnails');
        $criteria->addAssociation('mediaFolder.configuration.mediaThumbnailSizes');

        $media = $this->mediaRepository->search($criteria, $this->context)->get($media->getId());

        static::assertInstanceOf(MediaEntity::class, $media);

        $resource = fopen(__DIR__ . '/../fixtures/shopware-logo.png', 'r');
        \assert($resource !== false);

        $url = $media->getPath();

        $this->getPublicFilesystem()->writeStream($url, $resource);

        $thumbnailService = $this->getContainer()->get(ThumbnailService::class);

        $thumbnailService->generate(new MediaCollection([$media]), $this->context);

        $criteria = new Criteria([$media->getId()]);
        $criteria->addAssociation('thumbnails');

        $media = $this->mediaRepository
            ->search($criteria, $this->context)
            ->get($media->getId());

        static::assertInstanceOf(MediaEntity::class, $media);

        $thumbnails = $media->getThumbnails();

        static::assertInstanceOf(MediaThumbnailCollection::class, $thumbnails);
        static::assertEquals(2, $thumbnails->count());

        $filteredThumbnails = $thumbnails->filter(fn (MediaThumbnailEntity $thumbnail) => ($thumbnail->getWidth() === 300 && $thumbnail->getHeight() === 300)
            || ($thumbnail->getWidth() === 150 && $thumbnail->getHeight() === 150));

        static::assertEquals(2, $filteredThumbnails->count());

        /** @var MediaThumbnailEntity $thumbnail */
        foreach ($filteredThumbnails as $thumbnail) {
            $path = $thumbnail->getPath();
            static::assertTrue(
                $this->getPublicFilesystem()->has($path),
                'Thumbnail: ' . $path . ' does not exist'
            );
        }
    }

    /**
     * @return array<array<bool>>
     */
    public static function strictModeConditionsProvider(): array
    {
        return [[true], [false]];
    }

    #[DataProvider('strictModeConditionsProvider')]
    public function testUpdateThumbnailStrictMode(bool $strict): void
    {
        if ($this->remoteThumbnailsEnable) {
            static::markTestSkipped('Remote thumbnails is enabled. Skipping thumbnail generation test.');
        }

        $this->setFixtureContext($this->context);
        $media = $this->getPngWithFolder();

        $this->thumbnailRepository->create([
            [
                'mediaId' => $media->getId(),
                'width' => 200,
                'height' => 200,
            ],
        ], $this->context);

        $criteria = new Criteria([$media->getId()]);
        $criteria->addAssociation('thumbnails');
        $criteria->addAssociation('mediaFolder.configuration.mediaThumbnailSizes');

        $media = $this->mediaRepository->search($criteria, $this->context)->get($media->getId());

        static::assertInstanceOf(MediaEntity::class, $media);

        $resource = fopen(__DIR__ . '/../fixtures/shopware-logo.png', 'r');
        \assert($resource !== false);

        $location = $media->getPath();

        $this->getPublicFilesystem()->writeStream($location, $resource);

        $this->thumbnailService->generate(new MediaCollection([$media]), $this->context);

        // ensure synchronous file deletion is not set to be able to test updating thumbnails correctly
        $this->context->removeState(MediaDeletionSubscriber::SYNCHRONE_FILE_DELETE);

        $criteria = new Criteria([$media->getId()]);
        $criteria->addAssociation('thumbnails');

        $media = $this->mediaRepository
            ->search($criteria, $this->context)
            ->get($media->getId());

        static::assertInstanceOf(MediaEntity::class, $media);

        $thumbnail = $media->getThumbnails()?->first();

        static::assertInstanceOf(MediaThumbnailEntity::class, $thumbnail);

        $thumbnailPath = $thumbnail->getPath();

        static::assertTrue($this->getPublicFilesystem()->has($thumbnailPath));

        $this->getPublicFilesystem()->delete($thumbnailPath);

        $this->thumbnailService->updateThumbnails($media, $this->context, $strict);

        if ($strict) {
            static::assertTrue(
                $this->getPublicFilesystem()->has($thumbnailPath),
                'Thumbnail: ' . $thumbnailPath . ' does not exist, but it should be regenerated when in strict mode.'
            );

            $this->runWorker();

            static::assertTrue(
                $this->getPublicFilesystem()->has($thumbnailPath),
                'Thumbnail: ' . $thumbnailPath . ' should not be deleted asynchronous after regeneration when in strict mode.'
            );
        } else {
            static::assertFalse(
                $this->getPublicFilesystem()->has($thumbnailPath),
                'Thumbnail: ' . $thumbnailPath . ' does exist, but it should not be regenerated when not in strict mode.'
            );
        }
    }

    public function testGenerate(): void
    {
        $ids = new IdsCollection();

        $media = [
            'id' => $ids->get('media'),
            'fileName' => 'shopware-logo.png',
            'fileExtension' => 'png',
            'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        $this->getContainer()->get('media.repository')
            ->upsert([$media], Context::createDefaultContext());

        $media = $this->getContainer()->get('media.repository')
            ->search(new Criteria([$ids->get('media')]), Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(MediaEntity::class, $media);

        $resource = fopen(__DIR__ . '/../fixtures/shopware-logo.png', 'r');
        \assert($resource !== false);

        $this->getFilesystem('shopware.filesystem.public')->writeStream($media->getPath(), $resource);

        $service = $this->getContainer()->get(ThumbnailService::class);
    }
}
