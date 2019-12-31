<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class MediaThumbnailRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;
    use QueueTestBehaviour;

    private const FIXTURE_FILE = __DIR__ . '/../fixtures/shopware-logo.png';

    /**
     * @var EntityRepositoryInterface
     */
    private $mediaRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $thumbnailRepository;

    /**
     * @var Context
     */
    private $context;

    protected function setUp(): void
    {
        $this->mediaRepository = $this->getContainer()->get('media.repository');
        $this->thumbnailRepository = $this->getContainer()->get('media_thumbnail.repository');

        $this->context = Context::createDefaultContext();
    }

    public function testRemoveThumbnail(): void
    {
        $mediaId = Uuid::randomHex();
        $media = $this->createThumbnailWithMedia($mediaId);
        $thumbnailPath = $this->createThumbnailFile($media);

        $thumbnailIds = $this->thumbnailRepository->searchIds(new Criteria(), $this->context);
        $this->thumbnailRepository->delete($thumbnailIds->getIds(), $this->context);
        $this->runWorker();

        static::assertFalse($this->getPublicFilesystem()->has($thumbnailPath));
    }

    public function testRemoveThumbnailFromMedia(): void
    {
        $mediaId = Uuid::randomHex();
        $media = $this->createThumbnailWithMedia($mediaId);
        $thumbnailPath = $this->createThumbnailFile($media);

        $this->thumbnailRepository->delete($media->getThumbnails()->getIds(), $this->context);
        $this->runWorker();

        static::assertFalse($this->getPublicFilesystem()->has($thumbnailPath));
    }

    private function createThumbnailWithMedia(string $mediaId): MediaEntity
    {
        $this->mediaRepository->create([
            [
                'id' => $mediaId,
                'name' => 'test media',
                'fileExtension' => 'png',
                'mimeType' => 'image/png',
                'fileName' => $mediaId . '-' . (new \DateTime())->getTimestamp(),
                'thumbnails' => [
                    [
                        'width' => 100,
                        'height' => 200,
                        'highDpi' => false,
                    ],
                ],
            ],
        ], $this->context);

        return $this->mediaRepository->search(new Criteria([$mediaId]), $this->context)->get($mediaId);
    }

    private function createThumbnailFile(MediaEntity $media)
    {
        $thumbnailPath = $this->getContainer()->get(UrlGeneratorInterface::class)->getRelativeThumbnailUrl(
            $media,
            (new MediaThumbnailEntity())->assign(['width' => 100, 'height' => 200])
        );

        $this->getPublicFilesystem()->putStream($thumbnailPath, fopen(self::FIXTURE_FILE, 'rb'));

        return $thumbnailPath;
    }
}
