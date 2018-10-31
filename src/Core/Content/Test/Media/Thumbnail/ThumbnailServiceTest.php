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
use Shopware\Core\Content\Test\Media\MediaFixtures;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class ThumbnailServiceTest extends TestCase
{
    use IntegrationTestBehaviour, MediaFixtures;

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
     * @var ThumbnailService
     */
    private $thumbnailService;

    /**
     * @var RepositoryInterface
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
        $this->setFixtureContext($this->context);
        $media = $this->getPng();

        $filePath = $this->urlGenerator->getRelativeMediaUrl($media->getId(), $media->getFileExtension());
        $this->getPublicFilesystem()->putStream($filePath, fopen(__DIR__ . '/../fixtures/shopware-logo.png', 'r'));

        $this->thumbnailService->generateThumbnails(
            $media,
            $this->context
        );

        $searchCriteria = new Criteria();
        $searchCriteria->setLimit(1);
        $searchCriteria->addFilter(new EqualsFilter('media.id', $media->getId()));

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
                $media->getId(),
                $media->getFileExtension(),
                $thumbnail->getWidth(),
                $thumbnail->getHeight()
            );
            static::assertTrue($this->getPublicFilesystem()->has($thumbnailPath));

            if ($thumbnail->getHighDpi()) {
                $thumbnailPath = $this->urlGenerator->getRelativeThumbnailUrl(
                    $media->getId(),
                    $media->getFileExtension(),
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
        $this->setFixtureContext($this->context);
        $media = $this->getPng();

        $this->expectException(FileNotFoundException::class);
        $this->thumbnailService->generateThumbnails(
            $media,
            $this->context
        );
    }

    public function testGeneratorThrowsExceptionIfFileIsNoImage(): void
    {
        $this->setFixtureContext($this->context);
        $media = $this->getPng();

        $filePath = $this->urlGenerator->getRelativeMediaUrl($media->getId(), $media->getFileExtension());
        $this->getPublicFilesystem()->put($filePath, 'this is the content of the file, which is not a image');

        $this->expectException(FileTypeNotSupportedException::class);
        $this->thumbnailService->generateThumbnails(
            $media,
            $this->context
        );
    }

    public function testDeleteThumbnails_withSavedThumbnails()
    {
        $mediaId = Uuid::uuid4()->getHex();
        $mediaExtension = 'png';
        $mediaCriteria = new Criteria();
        $mediaCriteria->addFilter(new EqualsFilter('id', $mediaId));

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

        $this->getPublicFilesystem()->put($mediaUrl, 'test content');

        $thumbnailUrls = [];
        foreach ($media->getThumbnails() as $thumbnail) {
            $thumbnailUrl = $this->urlGenerator->getRelativeThumbnailUrl(
                $mediaId,
                $mediaExtension,
                $thumbnail->getWidth(),
                $thumbnail->getHeight(),
                $thumbnail->getHighDpi()
            );
            $this->getPublicFilesystem()->put($thumbnailUrl, 'test content');
            $thumbnailUrls[] = $thumbnailUrl;
        }

        $this->thumbnailService->deleteThumbnails($media, $this->context);

        // refresh entity
        $searchResult = $this->mediaRepository->search($mediaCriteria, $this->context);
        $media = $searchResult->getEntities()->get($mediaId);

        self::assertSame(0, $media->getThumbnails()->count());
        self::assertTrue($this->getPublicFilesystem()->has($mediaUrl));
        foreach ($thumbnailUrls as $thumbnailUrl) {
            self::assertFalse($this->getPublicFilesystem()->has($thumbnailUrl));
        }
    }
}
