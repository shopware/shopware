<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Media\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('discovery')]
class ThumbnailReadProjectionTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testReadsCurrentThumbnailsAndHonorsFilteredAssociation(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $mediaId = Uuid::randomHex();
        $thumbnailId = Uuid::randomHex();
        $created = '2026-01-02 03:04:05.000';
        $connection->insert('media', [
            'id' => Uuid::fromHexToBytes($mediaId),
            'path' => 'media/cover.jpg',
            'private' => 0,
            'created_at' => $created,
        ]);
        $sizeId = Uuid::randomBytes();
        $connection->insert('media_thumbnail_size', [
            'id' => $sizeId,
            'width' => 200,
            'height' => 100,
            'created_at' => $created,
        ]);
        $caption = 'Front view of the "Café" grinder';
        $connection->insert('media_thumbnail', [
            'custom_fields' => json_encode(['caption' => $caption], \JSON_THROW_ON_ERROR),
            'media_thumbnail_size_id' => $sizeId,
            'id' => Uuid::fromHexToBytes($thumbnailId),
            'media_id' => Uuid::fromHexToBytes($mediaId),
            'path' => 'thumbnail/cover.jpg',
            'width' => 200,
            'height' => 100,
            'created_at' => $created,
        ]);

        $repository = static::getContainer()->get('media.repository');
        $context = Context::createDefaultContext();
        $criteria = new Criteria([$mediaId]);
        $projected = $repository->search($criteria, $context)->getEntities()->first();
        static::assertInstanceOf(MediaEntity::class, $projected);
        $thumbnail = $projected->getThumbnails()?->get($thumbnailId);
        static::assertNotNull($thumbnail);
        static::assertSame($caption, $thumbnail->getCustomFieldsValue('caption'));
        static::assertStringContainsString('thumbnail/cover.jpg', $thumbnail->getUrl());

        $criteria->addAssociation('thumbnails');
        $explicit = $repository->search($criteria, $context)->getEntities()->first();
        static::assertInstanceOf(MediaEntity::class, $explicit);
        $explicitThumbnail = $explicit->getThumbnails()?->get($thumbnailId);
        static::assertNotNull($explicitThumbnail);
        static::assertSame($caption, $explicitThumbnail->getCustomFieldsValue('caption'));
        static::assertSame($thumbnail->getUrl(), $explicitThumbnail->getUrl());
        static::assertNull($explicit->getThumbnailsRo());

        $criteria->getAssociation('thumbnails')->addFilter(new EqualsFilter('width', 400));
        $filtered = $repository->search($criteria, $context)->getEntities()->first();
        static::assertInstanceOf(MediaEntity::class, $filtered);
        static::assertCount(0, $filtered->getThumbnails() ?? []);

        $connection->delete('media_thumbnail', ['id' => Uuid::fromHexToBytes($thumbnailId)]);
        $deleted = $repository->search(new Criteria([$mediaId]), $context)->getEntities()->first();
        static::assertInstanceOf(MediaEntity::class, $deleted);
        static::assertSame([], $deleted->getThumbnails()?->getIds());
    }
}
