<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Thumbnail;

use League\Flysystem\FileNotFoundException;
use League\Flysystem\Filesystem;
use League\Flysystem\Memory\MemoryAdapter;
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
     * @varFilesystem
     */
    private $fileSystem;

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
     * @var string
     */
    private $mediaId;

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
        $this->fileSystem = new Filesystem(new MemoryAdapter());
        $this->urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);
        $this->repository = $this->getContainer()->get('media.repository');
        $this->thumbnailConfiguration = $this->getContainer()->get(ThumbnailConfiguration::class);
        $this->context = Context::createDefaultContext(Defaults::TENANT_ID);

        $this->thumbnailService = new ThumbnailService($this->repository, $this->fileSystem, $this->urlGenerator, $this->thumbnailConfiguration);

        $this->mediaId = Uuid::uuid4()->getHex();
        $this->createTestEntity();
    }

    public function testSubscribesToMediaFileUploadedEvent()
    {
        static::assertArrayHasKey(MediaFileUploadedEvent::EVENT_NAME, $this->thumbnailService->getSubscribedEvents());
    }

    public function testThumbnailGeneration()
    {
        $mimeType = 'image/png';

        $filePath = $this->urlGenerator->getRelativeMediaUrl($this->mediaId, 'png');
        $this->fileSystem->putStream($filePath, fopen(__DIR__ . '/../fixtures/shopware-logo.png', 'r'));

        $this->thumbnailService->generateThumbnails(
            $this->mediaId,
            $mimeType,
            'png',
            $this->context
        );

        $searchCriteria = new Criteria();
        $searchCriteria->setLimit(1);
        $searchCriteria->addFilter(new TermQuery('media.id', $this->mediaId));

        $mediaResult = $this->repository->search($searchCriteria, $this->context);
        /** @var MediaStruct $updatedMedia */
        $updatedMedia = $mediaResult->getEntities()->first();

        $expectedNumberOfThumbnails = count($this->thumbnailConfiguration->getThumbnailSizes());
        if ($this->thumbnailConfiguration->isHighDpi()) {
            $expectedNumberOfThumbnails *= 2;
        }

        $thumbnails = $updatedMedia->getThumbnails();
        static::assertEquals(
            $expectedNumberOfThumbnails,
            $thumbnails->count()
        );

        foreach ($thumbnails as $thumbnail) {
            $thumbnailPath = $this->urlGenerator->getAbsoluteThumbnailUrl(
                $this->mediaId,
                'png',
                $thumbnail->getWidth(),
                $thumbnail->getHeight(),
                false,
                false);
            static::assertTrue($this->fileSystem->has($thumbnailPath));

            if ($thumbnail->getHighDpi()) {
                $thumbnailPath = $this->urlGenerator->getAbsoluteThumbnailUrl(
                    $this->mediaId,
                    'png',
                    $thumbnail->getWidth(),
                    $thumbnail->getHeight(),
                    true,
                    false);
                static::assertTrue($this->fileSystem->has($thumbnailPath));
            }
        }
    }

    public function testGeneratorThrowsExceptionIfFileDoesNotExist()
    {
        $mimeType = 'image/png';

        self::expectException(FileNotFoundException::class);
        $this->thumbnailService->generateThumbnails(
            $this->mediaId,
            $mimeType,
            'png',
            $this->context
        );
    }

    public function testGeneratorThrowsExceptionIfFileIsNoImage()
    {
        $mimeType = 'image/png';

        $filePath = $this->urlGenerator->getRelativeMediaUrl($this->mediaId, 'png');
        $this->fileSystem->put($filePath, 'this is the content of the file, which is not a image');

        self::expectException(FileTypeNotSupportedException::class);
        $this->thumbnailService->generateThumbnails(
            $this->mediaId,
            $mimeType,
            'png',
            $this->context
        );
    }

    private function createTestEntity()
    {
        $media = [
            'id' => $this->mediaId,
            'name' => 'test_media',
        ];

        $this->repository->create([$media], $this->context);
    }
}
