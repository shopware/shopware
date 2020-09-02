<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Thumbnail;

use League\Flysystem\FileNotFoundException;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeEntity;
use Shopware\Core\Content\Media\Exception\FileTypeNotSupportedException;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaType\DocumentType;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Content\Media\Thumbnail\ThumbnailService;
use Shopware\Core\Content\Test\Media\MediaFixtures;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class ThumbnailServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use MediaFixtures;
    use QueueTestBehaviour;

    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var ThumbnailService
     */
    private $thumbnailService;

    /**
     * @var EntityRepositoryInterface
     */
    private $mediaRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $thumbnailRepository;

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
        $this->getPublicFilesystem()->putStream($filePath, fopen(__DIR__ . '/../fixtures/shopware-logo.png', 'rb'));

        $this->thumbnailService->updateThumbnails(
            $media,
            $this->context
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
        static::assertEquals(
            2,
            $thumbnails->count()
        );

        foreach ($thumbnails as $thumbnail) {
            $thumbnailPath = $this->urlGenerator->getRelativeThumbnailUrl(
                $media,
                $thumbnail
            );
            $filtered = $updatedMedia->getMediaFolder()
                ->getConfiguration()
                ->getMediaThumbnailSizes()
                ->filter(function ($size) use ($thumbnail) {
                    return $size->getWidth() === $thumbnail->getWidth() && $size->getHeight() === $thumbnail->getHeight();
                });
            static::assertCount(1, $filtered);
            static::assertTrue($this->getPublicFilesystem()->has($thumbnailPath));
        }
    }

    public function testGeneratorThrowsExceptionIfFileDoesNotExist(): void
    {
        $this->setFixtureContext($this->context);
        $media = $this->getPngWithFolder();

        $this->expectException(FileNotFoundException::class);
        $this->thumbnailService->updateThumbnails(
            $media,
            $this->context
        );
    }

    public function testGeneratorThrowsExceptionIfFileIsNoImage(): void
    {
        $this->setFixtureContext($this->context);
        $media = $this->getPngWithFolder();

        $filePath = $this->urlGenerator->getRelativeMediaUrl($media);
        $this->getPublicFilesystem()->put($filePath, 'this is the content of the file, which is not a image');

        $this->expectException(FileTypeNotSupportedException::class);
        $this->thumbnailService->updateThumbnails(
            $media,
            $this->context
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
        $this->getPublicFilesystem()->putStream($filePath, fopen(__DIR__ . '/../fixtures/shopware_optimized.jpg', 'rb'));

        $this->thumbnailService->updateThumbnails(
            $media,
            $this->context
        );

        $this->runWorker();

        /** @var MediaEntity $updatedMedia */
        $updatedMedia = $this->mediaRepository->search(new Criteria([$media->getId()]), $this->context)->get($media->getId());

        $thumbnails = $updatedMedia->getThumbnails();
        static::assertEquals(
            1,
            $thumbnails->count()
        );

        $thumbnailPath = $this->urlGenerator->getRelativeThumbnailUrl(
            $media,
            $thumbnails->first()
        );

        static::assertTrue($this->getPublicFilesystem()->has($thumbnailPath));

        $originalSize = $this->getPublicFilesystem()->getSize($filePath);
        $thumbnailSize = $this->getPublicFilesystem()->getSize($thumbnailPath);
        static::assertLessThanOrEqual($originalSize, $thumbnailSize);
    }

    public function testItUsesFolderConfigGenerateThumbnails(): void
    {
        $this->setFixtureContext($this->context);
        $media = $this->getJpgWithFolderWithoutThumbnails();

        $filePath = $this->urlGenerator->getRelativeMediaUrl($media);
        $this->getPublicFilesystem()->putStream($filePath, fopen(__DIR__ . '/../fixtures/shopware.jpg', 'rb'));

        $this->thumbnailService->updateThumbnails(
            $media,
            $this->context
        );

        /** @var MediaEntity $updatedMedia */
        $updatedMedia = $this->mediaRepository->search(new Criteria([$media->getId()]), $this->context)->get($media->getId());

        $thumbnails = $updatedMedia->getThumbnails();
        static::assertEquals(
            0,
            $thumbnails->count()
        );
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

        static::assertSame(2, $media->getThumbnails()->count());

        $this->getPublicFilesystem()->put($mediaUrl, 'test content');

        $thumbnailUrls = [];
        foreach ($media->getThumbnails() as $thumbnail) {
            $thumbnailUrl = $this->urlGenerator->getRelativeThumbnailUrl(
                $media,
                $thumbnail
            );
            $this->getPublicFilesystem()->put($thumbnailUrl, 'test content');
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
            $this->context
        ));
    }

    public function testThumbnailGenerationThrowsExceptionIfFileIsVectorGraphic(): void
    {
        $this->setFixtureContext($this->context);
        $media = $this->getPng();
        $media->getMediaType()->addFlag(ImageType::VECTOR_GRAPHIC);

        static::assertEquals(0, $this->thumbnailService->updateThumbnails(
            $media,
            $this->context
        ));
    }

    public function testThumbnailGenerationThrowsExceptionIfFileIsAnimated(): void
    {
        $this->setFixtureContext($this->context);
        $media = $this->getPng();
        $media->getMediaType()->addFlag(ImageType::ANIMATED);

        static::assertEquals(0, $this->thumbnailService->updateThumbnails(
            $media,
            $this->context
        ));
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

        $this->getPublicFilesystem()->putStream(
            $this->urlGenerator->getRelativeMediaUrl($media),
            fopen(__DIR__ . '/../fixtures/shopware-logo.png', 'rb')
        );

        $this->thumbnailService->generateThumbnails($media, $this->context);

        $criteria = new Criteria([$media->getId()]);
        $criteria->addAssociation('thumbnails');

        $media = $this->mediaRepository
            ->search($criteria, $this->context)
            ->get($media->getId());

        static::assertEquals(2, $media->getThumbnails()->count());

        $filteredThumbnails = $media->getThumbnails()->filter(function (MediaThumbnailEntity $thumbnail) {
            return ($thumbnail->getWidth() === 300 && $thumbnail->getHeight() === 300)
                || ($thumbnail->getWidth() === 150 && $thumbnail->getHeight() === 150);
        });

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

        $this->getPublicFilesystem()->putStream(
            $this->urlGenerator->getRelativeMediaUrl($media),
            fopen(__DIR__ . '/../fixtures/shopware-logo.png', 'rb')
        );

        $this->thumbnailService->generateThumbnails($media, $this->context);

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
            [ $width, $height ] = getimagesizefromstring($fileContents);
            static::assertSame(499, $width);
            static::assertSame(266, $height);
        }
    }
}
