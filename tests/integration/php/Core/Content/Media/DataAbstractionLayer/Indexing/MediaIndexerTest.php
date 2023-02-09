<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Media\DataAbstractionLayer\Indexing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class MediaIndexerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepository $mediaRepository;

    public function setUp(): void
    {
        $this->mediaRepository = $this->getContainer()->get('media.repository');
    }

    public function testNewMediaHasUpdatedFields(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();

        $data = [
            'id' => $ids->get('media'),
            'name' => 'test media',
            'mimeType' => 'image/png',
            'fileExtension' => 'png',
            'fileName' => $ids->get('media') . '-' . (new \DateTime())->getTimestamp(),
            'private' => false,
            'thumbnails' => [
                [
                    'width' => 100,
                    'height' => 200,
                    'highDpi' => false,
                ],
            ],
        ];

        $this->mediaRepository->create([
            $data,
        ], $context);

        /** @var MediaEntity $media */
        $media = $this->mediaRepository
            ->search(new Criteria([$ids->get('media')]), $context)
            ->first();

        static::assertInstanceOf(MediaEntity::class, $media);

        static::assertNotEmpty($media->getPath());
        static::assertStringEndsWith($data['fileName'] . '.' . $data['fileExtension'], $media->getPath());

        static::assertNotEmpty($media->getThumbnails());
        static::assertNotEmpty($media->getThumbnails()->first()->getPath());

        static::assertNotEmpty($media->getThumbnailsRo());

        $thumbnailsRo = \unserialize($media->getThumbnailsRo());
        static::assertInstanceOf(MediaThumbnailCollection::class, $thumbnailsRo);

        static::assertNotEmpty($media->getThumbnails());
        static::assertSame($media->getThumbnails()->first()->getPath(), $thumbnailsRo->first()->getPath());
    }
}
