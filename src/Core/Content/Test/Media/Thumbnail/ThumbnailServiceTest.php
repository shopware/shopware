<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Thumbnail;

use League\Flysystem\FileNotFoundException;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Shopware\Core\Content\Media\Exception\FileTypeNotSupportedException;
use Shopware\Core\Content\Media\MediaProtectionFlags;
use Shopware\Core\Content\Media\MediaStruct;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Content\Media\Thumbnail\ThumbnailConfiguration;
use Shopware\Core\Content\Media\Thumbnail\ThumbnailService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\EntityRepository;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class ThumbnailServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    /**
     * @var ThumbnailConfiguration
     */
    private $thumbnailConfiguration;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var MediaStruct
     */
    private $media;

    /**
     * @var ThumbnailService
     */
    private $thumbnailService;

    /**
     * @var EntityRepository
     */
    private $mediaRepository;

    public function setUp()
    {
        $this->urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);
        $this->mediaRepository = $this->getContainer()->get('media.repository');
        $this->thumbnailConfiguration = $this->getContainer()->get(ThumbnailConfiguration::class);
        $this->context = Context::createDefaultContext(Defaults::TENANT_ID);
        $this->context->getWriteProtection()->allow(MediaProtectionFlags::WRITE_META_INFO);

        $this->thumbnailService = $this->getContainer()->get(ThumbnailService::class);
    }

    public function testThumbnailGeneration(): void
    {
        $testMedia = $this->createTestEntity();
        $filePath = $this->urlGenerator->getRelativeMediaUrl($testMedia->getId(), $testMedia->getFileExtension());
        $this->getPublicFilesystem()->putStream($filePath, fopen(__DIR__ . '/../fixtures/shopware-logo.png', 'r'));

        $this->thumbnailService->generateThumbnails(
            $testMedia,
            $this->context
        );

        $searchCriteria = new Criteria();
        $searchCriteria->setLimit(1);
        $searchCriteria->addFilter(new TermQuery('media.id', $testMedia->getId()));

        $mediaResult = $this->mediaRepository->search($searchCriteria, $this->context);
        /** @var MediaStruct $updatedMedia */
        $updatedMedia = $mediaResult->getEntities()->first();

        $expectedNumberOfThumbnails = \count($this->thumbnailConfiguration->getThumbnailSizes());
        if ($this->thumbnailConfiguration->isHighDpi()) {
            $expectedNumberOfThumbnails *= 2;
        }

        $thumbnails = $updatedMedia->getThumbnails();
        static::assertEquals(
            $expectedNumberOfThumbnails,
            $thumbnails->count()
        );

        foreach ($thumbnails as $thumbnail) {
            $thumbnailPath = $this->urlGenerator->getRelativeThumbnailUrl(
                $testMedia->getId(),
                $testMedia->getFileExtension(),
                $thumbnail->getWidth(),
                $thumbnail->getHeight()
            );
            static::assertTrue($this->getPublicFilesystem()->has($thumbnailPath));

            if ($thumbnail->getHighDpi()) {
                $thumbnailPath = $this->urlGenerator->getRelativeThumbnailUrl(
                    $testMedia->getId(),
                    $testMedia->getFileExtension(),
                    $thumbnail->getWidth(),
                    $thumbnail->getHeight(),
                    true
                );
                static::assertTrue($this->getPublicFilesystem()->has($thumbnailPath));
            }
        }
    }

    public function testGeneratorThrowsExceptionIfFileDoesNotExist(): void
    {
        $testMedia = $this->createTestEntity();
        $this->expectException(FileNotFoundException::class);
        $this->thumbnailService->generateThumbnails(
            $testMedia,
            $this->context
        );
    }

    public function testGeneratorThrowsExceptionIfFileIsNoImage(): void
    {
        $testMedia = $this->createTestEntity();
        $filePath = $this->urlGenerator->getRelativeMediaUrl($testMedia->getId(), $testMedia->getFileExtension());
        $this->getPublicFilesystem()->put($filePath, 'this is the content of the file, which is not a image');

        $this->expectException(FileTypeNotSupportedException::class);
        $this->thumbnailService->generateThumbnails(
            $testMedia,
            $this->context
        );
    }

    public function testDeleteThumbnailFiles_deletesFilesWithThumbnailExtension()
    {
        $testId = Uuid::uuid4()->getHex();
        $testExtension = 'png';

        $mediaFilePath = $this->urlGenerator->getRelativeMediaUrl($testId, $testExtension);
        $thumbnailPaths = [
            $this->urlGenerator->getRelativeThumbnailUrl($testId, $testExtension, 100, 150, false, false),
            $this->urlGenerator->getRelativeThumbnailUrl($testId, $testExtension, 140, 140, true, false),
        ];

        $this->fileSystem->put($mediaFilePath, 'testContent');
        foreach ($thumbnailPaths as $thumbnailPath) {
            $this->fileSystem->put($thumbnailPath, 'testContent');
        }

        $this->thumbnailService->deleteThumbnailFiles($testId);

        foreach ($thumbnailPaths as $thumbnailPath) {
            static::assertFalse($this->fileSystem->has($thumbnailPath));
        }
        static::assertTrue($this->fileSystem->has($mediaFilePath));
    }

    public function testDeleteThumbnailFiles_ignoresFilesFromOtherMediaIds()
    {
        $testId = Uuid::uuid4()->getHex();
        $otherId = Uuid::uuid4()->getHex();
        $testExtension = 'png';

        $thumbnailFolder = pathinfo(
            $this->urlGenerator->getRelativeThumbnailUrl($testId, $testExtension, 100, 150, false, false),
            PATHINFO_DIRNAME
        );

        $thumbnailPaths = [
            $thumbnailFolder . $otherId . '_120_120@2x.png',
            $thumbnailFolder . $otherId . '_120_120.png',
        ];

        foreach ($thumbnailPaths as $thumbnailPath) {
            $this->fileSystem->put($thumbnailPath, 'test content');
        }

        $this->thumbnailService->deleteThumbnailFiles($testId);

        foreach ($thumbnailPaths as $thumbnailPath) {
            static::assertTrue($this->fileSystem->has($thumbnailPath));
        }
    }

    public function testDeleteThumbnails_withSavedThumbnails()
    {
        $mediaId = Uuid::uuid4()->getHex();
        $mediaExtension = 'png';
        $mediaCriteria = new Criteria();
        $mediaCriteria->addFilter(new TermQuery('id', $mediaId));

        $this->context->getWriteProtection()->allow(MediaProtectionFlags::WRITE_THUMBNAILS);

        $this->mediaRepository->create([
            [
                'id' => $mediaId,
                'name' => 'media without thumbnails',
                'fileExtension' => $mediaExtension,
                'mimeType' => 'image/png',
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

        $this->context->getWriteProtection()->disallow(MediaProtectionFlags::WRITE_THUMBNAILS);

        $searchResult = $this->mediaRepository->search($mediaCriteria, $this->context);
        /** @var MediaStruct $media */
        $media = $searchResult->getEntities()->get($mediaId);
        $mediaUrl = $this->urlGenerator->getRelativeMediaUrl($media->getId(), $media->getFileExtension());

        self::assertSame(2, $media->getThumbnails()->count());

        $this->fileSystem->put($mediaUrl, 'test content');

        $thumbnailUrls = [];
        foreach ($media->getThumbnails() as $thumbnail) {
            $thumbnailUrl = $this->urlGenerator->getRelativeThumbnailUrl(
                $mediaId,
                $mediaExtension,
                $thumbnail->getWidth(),
                $thumbnail->getHeight(),
                $thumbnail->getHighDpi()
            );
            $this->fileSystem->put($thumbnailUrl, 'test content');
            $thumbnailUrls[] = $thumbnailUrl;
        }

        $this->thumbnailService->deleteThumbnails($media, $this->context);

        // refresh entity
        $searchResult = $this->mediaRepository->search($mediaCriteria, $this->context);
        $media = $searchResult->getEntities()->get($mediaId);

        self::assertSame(0, $media->getThumbnails()->count());
        self::assertTrue($this->fileSystem->has($mediaUrl));
        foreach ($thumbnailUrls as $thumbnailUrl) {
            self::assertFalse($this->fileSystem->has($thumbnailUrl));
        }
    }

    private function createTestEntity(): MediaStruct
    {
        $media = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'test_media',
            'mimeType' => 'image/png',
            'fileExtension' => 'png',
        ];

        $this->mediaRepository->create([$media], $this->context);

        return (new MediaStruct())->assign($media);
    }
}
