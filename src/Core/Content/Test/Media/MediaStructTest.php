<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailStruct;
use Shopware\Core\Content\Media\MediaProtectionFlags;
use Shopware\Core\Content\Media\MediaStruct;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\EntityRepository;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Shopware\Core\Framework\ORM\Write\FieldException\WriteStackException;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class MediaStructTest extends TestCase
{
    use IntegrationTestBehaviour;

    /** @var Connection */
    private $connection;

    /** @var EntityRepository */
    private $repository;

    /** @var Context */
    private $context;

    public function setUp()
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->repository = $this->getContainer()->get('media.repository');
        $this->context = Context::createDefaultContext(Defaults::TENANT_ID);
    }

    public function testWriteReadMinimalFields(): void
    {
        $mediaId = Uuid::uuid4()->getHex();

        $mediaData = [
            'id' => $mediaId,
            'name' => 'test_media',
        ];

        $this->repository->create([$mediaData], $this->context);

        $criteria = $this->getIdCriteria($mediaId);
        $result = $this->repository->search($criteria, $this->context);
        $media = $result->getEntities()->first();

        static::assertInstanceOf(MediaStruct::class, $media);
        static::assertEquals($mediaId, $media->getId());
        static::assertEquals('test_media', $media->getName());
    }

    public function testMimeTypeIsWriteProtected(): void
    {
        $mediaId = Uuid::uuid4()->getHex();

        $mediaData = [
            'id' => $mediaId,
            'name' => 'test_media',
            'mimeType' => 'image/png',
        ];

        $this->expectException(WriteStackException::class);
        $this->repository->create([$mediaData], $this->context);
    }

    public function testThumbnailsIsWriteProtected(): void
    {
        $mediaId = Uuid::uuid4()->getHex();

        $mediaData = [
            'id' => $mediaId,
            'name' => 'test_media',
            'thumbnails' => [
                [
                    'width' => 200,
                    'height' => 200,
                    'highDpi' => false,
                ],
            ],
        ];

        $this->expectException(WriteStackException::class);
        $this->repository->create([$mediaData], $this->context);
    }

    public function testThumbnailsAreConvertedToStructWhenFetchedFromDb(): void
    {
        $mediaId = Uuid::uuid4()->getHex();

        $mediaData = [
            'id' => $mediaId,
            'name' => 'test_media',
            'thumbnails' => [
                [
                    'width' => 200,
                    'height' => 200,
                    'highDpi' => false,
                ],
            ],
        ];

        $this->context->getWriteProtection()->allow(MediaProtectionFlags::WRITE_THUMBNAILS);
        $this->repository->create([$mediaData], $this->context);

        $criteria = $this->getIdCriteria($mediaId);

        $searchResult = $this->repository->search($criteria, $this->context);
        $media = $searchResult->getEntities()->get($mediaId);

        static::assertEquals(MediaThumbnailCollection::class, \get_class($media->getThumbnails()));

        $persistedThumbnail = $media->getThumbnails()->first();
        static::assertEquals(MediaThumbnailStruct::class, \get_class($persistedThumbnail));
        static::assertEquals(200, $persistedThumbnail->getWidth());
        static::assertEquals(200, $persistedThumbnail->getHeight());
        static::assertFalse($persistedThumbnail->getHighDpi());
    }

    private function getIdCriteria($mediaId): Criteria
    {
        $criteria = new Criteria();
        $criteria->setOffset(0);
        $criteria->setLimit(1);
        $criteria->addFilter(new TermQuery('media.id', $mediaId));

        return $criteria;
    }
}
