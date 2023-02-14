<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Thumbnail;

use League\Flysystem\UnableToReadFile;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationEntity;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeEntity;
use Shopware\Core\Content\Media\Exception\ThumbnailNotSupportedException;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaType\DocumentType;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Content\Media\MediaType\MediaType;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Content\Media\Thumbnail\ThumbnailService;
use Shopware\Core\Content\Test\Media\MediaFixtures;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @group slow
 */
class ThumbnailServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use MediaFixtures;
    use QueueTestBehaviour;

    private UrlGeneratorInterface $urlGenerator;

    private Context $context;

    private ThumbnailService $thumbnailService;

    private EntityRepository $mediaRepository;

    private EntityRepository $thumbnailRepository;

    protected function setUp(): void
    {
        $this->urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);
        $this->mediaRepository = $this->getContainer()->get('media.repository');
        $this->thumbnailRepository = $this->getContainer()->get('media_thumbnail.repository');
        $this->context = Context::createDefaultContext();

        $this->thumbnailService = $this->getContainer()->get(ThumbnailService::class);
    }

    public function testThumbnailGeneration(): void
    {
        $this->setFixtureContext($this->context);
        $media = $this->getPngWithFolder();

        $filePath = $this->urlGenerator->getRelativeMediaUrl($media);
        $resource = fopen(__DIR__ . '/../fixtures/shopware-logo.png', 'rb');

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
            $thumbnailPath = $this->urlGenerator->getRelativeThumbnailUrl(
                $media,
                $thumbnail
            );

            $folder = $updatedMedia->getMediaFolder();
            static::assertInstanceOf(MediaFolderEntity::class, $folder);
            static::assertInstanceOf(MediaFolderConfigurationEntity::class, $folder->getConfiguration());

            $sizes = $folder->getConfiguration()->getMediaThumbnailSizes();
            static::assertInstanceOf(MediaThumbnailSizeCollection::class, $sizes);

            $filtered = $sizes->filter(fn ($size) => $size->getWidth() === $thumbnail->getWidth() && $size->getHeight() === $thumbnail->getHeight());

            static::assertCount(1, $filtered);
            static::assertTrue($this->getPublicFilesystem()->has($thumbnailPath));
        }
    }

    public function testGeneratorThrowsExceptionIfFileDoesNotExist(): void
    {
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
        $this->setFixtureContext($this->context);
        $media = $this->getPngWithFolder();

        $filePath = $this->urlGenerator->getRelativeMediaUrl($media);
        $this->getPublicFilesystem()->write($filePath, 'this is the content of the file, which is not a image');

        $this->expectException(ThumbnailNotSupportedException::class);
        $this->thumbnailService->updateThumbnails(
            $media,
            $this->context,
            false
        );
    }

    public function testItUsesOriginalImageIfItsSmallerThanGeneratedThumbnail(): void
    {
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

        $filePath = $this->urlGenerator->getRelativeMediaUrl($media);
        $resource = fopen(__DIR__ . '/../fixtures/shopware_optimized.jpg', 'rb');
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

        $thumbnailPath = $this->urlGenerator->getRelativeThumbnailUrl($media, $thumbnail);

        static::assertTrue($this->getPublicFilesystem()->has($thumbnailPath));

        $originalSize = $this->getPublicFilesystem()->fileSize($filePath);
        $thumbnailSize = $this->getPublicFilesystem()->fileSize($thumbnailPath);
        static::assertLessThanOrEqual($originalSize, $thumbnailSize);
    }

    public function testItUsesFolderConfigGenerateThumbnails(): void
    {
        $this->setFixtureContext($this->context);
        $media = $this->getJpgWithFolderWithoutThumbnails();

        $filePath = $this->urlGenerator->getRelativeMediaUrl($media);
        $resource = fopen(__DIR__ . '/../fixtures/shopware.jpg', 'rb');
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
        $mediaId = Uuid::randomHex();
        $mediaExtension = 'png';

        $this->mediaRepository->create(
            [
                [
                    'id' => $mediaId,
                    'name' => 'media without thumbnails',
                    'fileExtension' => $mediaExtension,
                    'mimeType' => 'image/png',
                    'fileName' => $mediaId . '-' . (new \DateTime())->getTimestamp(),
                    'thumbnails' => [
                        [
                            'width' => 100,
                            'height' => 100,
                            'highDpi' => false,
                        ],
                        [
                            'width' => 300,
                            'height' => 300,
                            'highDpi' => true,
                        ],
                    ],
                ],
            ],
            $this->context
        );

        /** @var MediaEntity $media */
        $media = $this->mediaRepository->search(new Criteria([$mediaId]), $this->context)->get($mediaId);
        $mediaUrl = $this->urlGenerator->getRelativeMediaUrl($media);

        static::assertInstanceOf(MediaThumbnailCollection::class, $media->getThumbnails());
        static::assertCount(2, $media->getThumbnails());

        $this->getPublicFilesystem()->write($mediaUrl, 'test content');

        static::assertInstanceOf(MediaThumbnailCollection::class, $media->getThumbnails());

        $thumbnailUrls = [];
        foreach ($media->getThumbnails() as $thumbnail) {
            $thumbnailUrl = $this->urlGenerator->getRelativeThumbnailUrl($media, $thumbnail);
            $this->getPublicFilesystem()->write($thumbnailUrl, 'test content');
            $thumbnailUrls[] = $thumbnailUrl;
        }

        $this->thumbnailService->deleteThumbnails($media, $this->context);

        $this->runWorker();

        // refresh entity
        $media = $this->mediaRepository->search(new Criteria([$mediaId]), $this->context)->get($mediaId);

        static::assertSame(0, $media->getThumbnails()->count());
        static::assertTrue($this->getPublicFilesystem()->has($mediaUrl));
        foreach ($thumbnailUrls as $thumbnailUrl) {
            static::assertFalse($this->getPublicFilesystem()->has($thumbnailUrl));
        }
    }

    public function testThumbnailGenerationThrowsExceptionIfFileTypeIsNotImage(): void
    {
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
        $this->setFixtureContext($this->context);
        $media = $this->getPng();
        static::assertInstanceOf(MediaType::class, $media->getMediaType());
        $media->getMediaType()->addFlag(ImageType::VECTOR_GRAPHIC);

        static::assertEquals(0, $this->thumbnailService->updateThumbnails($media, $this->context, false));
    }

    public function testThumbnailGenerationThrowsExceptionIfFileIsAnimated(): void
    {
        $this->setFixtureContext($this->context);
        $media = $this->getPng();
        static::assertInstanceOf(MediaType::class, $media->getMediaType());
        $media->getMediaType()->addFlag(ImageType::ANIMATED);

        static::assertEquals(0, $this->thumbnailService->updateThumbnails($media, $this->context, false));
    }

    public function testGenerateThumbnails(): void
    {
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

        $resource = fopen(__DIR__ . '/../fixtures/shopware-logo.png', 'rb');
        \assert($resource !== false);

        $this->getPublicFilesystem()->writeStream(
            $this->urlGenerator->getRelativeMediaUrl($media),
            $resource
        );

        $this->thumbnailService->generate(new MediaCollection([$media]), $this->context);

        $criteria = new Criteria([$media->getId()]);
        $criteria->addAssociation('thumbnails');

        $media = $this->mediaRepository
            ->search($criteria, $this->context)
            ->get($media->getId());

        static::assertEquals(2, $media->getThumbnails()->count());

        $filteredThumbnails = $media->getThumbnails()->filter(fn (MediaThumbnailEntity $thumbnail) => ($thumbnail->getWidth() === 300 && $thumbnail->getHeight() === 300)
            || ($thumbnail->getWidth() === 150 && $thumbnail->getHeight() === 150));

        static::assertEquals(2, $filteredThumbnails->count());

        /** @var MediaThumbnailEntity $thumbnail */
        foreach ($filteredThumbnails as $thumbnail) {
            $path = $this->urlGenerator->getRelativeThumbnailUrl($media, $thumbnail);
            static::assertTrue(
                $this->getPublicFilesystem()->has($path),
                'Thumbnail: ' . $path . ' does not exist'
            );
        }
    }

    public function testGenerateThumbnailsWithSmallerImageSizeThanThumbnailSize(): void
    {
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

        $resource = fopen(__DIR__ . '/../fixtures/shopware-logo.png', 'rb');
        \assert($resource !== false);

        $this->getPublicFilesystem()->writeStream(
            $this->urlGenerator->getRelativeMediaUrl($media),
            $resource
        );

        $this->thumbnailService->generate(new MediaCollection([$media]), $this->context);

        $criteria = new Criteria([$media->getId()]);
        $criteria->addAssociation('thumbnails');

        $media = $this->mediaRepository
            ->search($criteria, $this->context)
            ->get($media->getId());

        static::assertEquals(2, $media->getThumbnails()->count());

        /** @var MediaThumbnailEntity $thumbnail */
        foreach ($media->getThumbnails() as $thumbnail) {
            $path = $this->urlGenerator->getRelativeThumbnailUrl($media, $thumbnail);
            static::assertTrue(
                $this->getPublicFilesystem()->has($path),
                'Thumbnail: ' . $path . ' does not exist'
            );

            $fileContents = $this->getPublicFilesystem()->read($path);
            static::assertIsString($fileContents);
            $result = getimagesizefromstring($fileContents);

            static::assertIsArray($result);
            static::assertSame(499, $result[0]);
            static::assertSame(266, $result[1]);
        }
    }

    public function testGenerateThumbnailsWithSkipDeleteMessage(): void
    {
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

        $resource = fopen(__DIR__ . '/../fixtures/shopware-logo.png', 'rb');
        \assert($resource !== false);
        $this->getPublicFilesystem()->writeStream(
            $this->urlGenerator->getRelativeMediaUrl($media),
            $resource
        );

        $thumbnailService = $this->getContainer()->get(ThumbnailService::class);

        $thumbnailService->generate(new MediaCollection([$media]), $this->context);

        $criteria = new Criteria([$media->getId()]);
        $criteria->addAssociation('thumbnails');

        $media = $this->mediaRepository
            ->search($criteria, $this->context)
            ->get($media->getId());

        static::assertEquals(2, $media->getThumbnails()->count());

        $filteredThumbnails = $media->getThumbnails()->filter(fn (MediaThumbnailEntity $thumbnail) => ($thumbnail->getWidth() === 300 && $thumbnail->getHeight() === 300)
            || ($thumbnail->getWidth() === 150 && $thumbnail->getHeight() === 150));

        static::assertEquals(2, $filteredThumbnails->count());

        /** @var MediaThumbnailEntity $thumbnail */
        foreach ($filteredThumbnails as $thumbnail) {
            $path = $this->urlGenerator->getRelativeThumbnailUrl($media, $thumbnail);
            static::assertTrue(
                $this->getPublicFilesystem()->has($path),
                'Thumbnail: ' . $path . ' does not exist'
            );
        }
    }

    public static function strictModeConditionsProvider(): array
    {
        return [[true], [false]];
    }

    /**
     * @dataProvider strictModeConditionsProvider
     */
    public function testUpdateThumbnailStrictMode(bool $strict): void
    {
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

        $resource = fopen(__DIR__ . '/../fixtures/shopware-logo.png', 'rb');
        \assert($resource !== false);
        $this->getPublicFilesystem()->writeStream(
            $this->urlGenerator->getRelativeMediaUrl($media),
            $resource
        );

        $this->thumbnailService->generate(new MediaCollection([$media]), $this->context);

        $criteria = new Criteria([$media->getId()]);
        $criteria->addAssociation('thumbnails');

        $media = $this->mediaRepository
            ->search($criteria, $this->context)
            ->get($media->getId());

        $thumbnail = $media->getThumbnails()->first();
        $thumbnailPath = $this->urlGenerator->getRelativeThumbnailUrl($media, $thumbnail);

        if ($this->getPublicFilesystem()->has($thumbnailPath)) {
            // Make sure the file is deleted from filesystem
            $this->getPublicFilesystem()->delete($thumbnailPath);
        }

        $this->thumbnailService->updateThumbnails($media, $this->context, $strict);

        if ($strict) {
            static::assertTrue(
                $this->getPublicFilesystem()->has($thumbnailPath),
                'Thumbnail: ' . $thumbnailPath . ' does not exist, but it should be regenerated when in strict mode.'
            );
        } else {
            static::assertFalse(
                $this->getPublicFilesystem()->has($thumbnailPath),
                'Thumbnail: ' . $thumbnailPath . ' does exist, but it should not be regenerated when not in strict mode.'
            );
        }
    }
}
