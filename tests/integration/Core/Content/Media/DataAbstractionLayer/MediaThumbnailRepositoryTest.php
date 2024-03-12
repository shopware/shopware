<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Media\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class MediaThumbnailRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;
    use QueueTestBehaviour;

    #[DataProvider('deleteThumbnailProvider')]
    public function testDeleteThumbnail(bool $private): void
    {
        $service = $private ? 'shopware.filesystem.private' : 'shopware.filesystem.public';

        $mediaId = Uuid::randomHex();

        $media = $this->createThumbnailWithMedia($mediaId, $private);

        $thumbnailPath = $this->createThumbnailFile($media, $service);

        $thumbnailIds = static::getContainer()->get('media_thumbnail.repository')
            ->searchIds(new Criteria(), Context::createDefaultContext());

        $delete = \array_values(\array_map(static fn ($id) => ['id' => $id], $thumbnailIds->getIds()));

        static::getContainer()->get('media_thumbnail.repository')->delete($delete, Context::createDefaultContext());
        $this->runWorker();

        static::assertFalse($this->getFilesystem($service)->has($thumbnailPath));
    }

    public static function deleteThumbnailProvider(): \Generator
    {
        yield 'Test private filesystem' => [true];
        yield 'Test public filesystem' => [true];
    }

    private function createThumbnailWithMedia(string $mediaId, bool $private): MediaEntity
    {
        static::getContainer()->get('media.repository')->create([
            [
                'id' => $mediaId,
                'name' => 'test media',
                'fileExtension' => 'png',
                'mimeType' => 'image/png',
                'fileName' => $mediaId . '-' . (new \DateTime())->getTimestamp(),
                'private' => $private,
                'thumbnails' => [
                    [
                        'width' => 100,
                        'height' => 200,
                        'highDpi' => false,
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $media = static::getContainer()->get('media.repository')
            ->search(new Criteria([$mediaId]), Context::createDefaultContext())
            ->get($mediaId);

        static::assertInstanceOf(MediaEntity::class, $media);

        return $media;
    }

    private function createThumbnailFile(MediaEntity $media, string $service): string
    {
        $data = [
            'mediaId' => $media->getId(),
            'width' => 100,
            'height' => 200,
            'path' => 'foo/bar.png',
        ];

        static::getContainer()->get('media_thumbnail.repository')
            ->create([$data], Context::createDefaultContext());

        $fs = $this->getFilesystem($service);

        $fs->write('foo/bar.png', 'foo');

        static::assertTrue($fs->has('foo/bar.png'));

        return 'foo/bar.png';
    }
}
