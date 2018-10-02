<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Thumbnail;

use League\Flysystem\FileNotFoundException;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Shopware\Core\Content\Media\Event\MediaFileUploadedEvent;
use Shopware\Core\Content\Media\Exception\FileTypeNotSupportedException;
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
    private $repository;

    public function setUp()
    {
        $this->urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);
        $this->repository = $this->getContainer()->get('media.repository');
        $this->thumbnailConfiguration = $this->getContainer()->get(ThumbnailConfiguration::class);
        $this->context = Context::createDefaultContext(Defaults::TENANT_ID);
        $this->context->getExtension('write_protection')->set('write_media', true);

        $this->thumbnailService = $this->getContainer()->get(ThumbnailService::class);

        $this->media = $this->createTestEntity();
    }

    public function testSubscribesToMediaFileUploadedEvent(): void
    {
        static::assertArrayHasKey(MediaFileUploadedEvent::EVENT_NAME, $this->thumbnailService::getSubscribedEvents());
    }

    public function testThumbnailGeneration(): void
    {
        $filePath = $this->urlGenerator->getRelativeMediaUrl($this->media->getId(), $this->media->getFileExtension());
        $this->getPublicFilesystem()->putStream($filePath, fopen(__DIR__ . '/../fixtures/shopware-logo.png', 'r'));

        $this->thumbnailService->generateThumbnails(
            $this->media,
            $this->context
        );

        $searchCriteria = new Criteria();
        $searchCriteria->setLimit(1);
        $searchCriteria->addFilter(new TermQuery('media.id', $this->media->getId()));

        $mediaResult = $this->repository->search($searchCriteria, $this->context);
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
                $this->media->getId(),
                $this->media->getFileExtension(),
                $thumbnail->getWidth(),
                $thumbnail->getHeight()
            );
            static::assertTrue($this->getPublicFilesystem()->has($thumbnailPath));

            if ($thumbnail->getHighDpi()) {
                $thumbnailPath = $this->urlGenerator->getRelativeThumbnailUrl(
                    $this->media->getId(),
                    $this->media->getFileExtension(),
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
        $this->expectException(FileNotFoundException::class);
        $this->thumbnailService->generateThumbnails(
            $this->media,
            $this->context
        );
    }

    public function testGeneratorThrowsExceptionIfFileIsNoImage(): void
    {
        $filePath = $this->urlGenerator->getRelativeMediaUrl($this->media->getId(), $this->media->getFileExtension());
        $this->getPublicFilesystem()->put($filePath, 'this is the content of the file, which is not a image');

        $this->expectException(FileTypeNotSupportedException::class);
        $this->thumbnailService->generateThumbnails(
            $this->media,
            $this->context
        );
    }

    private function createTestEntity(): MediaStruct
    {
        $media = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'test_media',
            'mimeType' => 'image/png',
            'fileExtension' => 'png',
        ];

        $this->repository->create([$media], $this->context);

        return (new MediaStruct())->assign($media);
    }
}
