<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaStruct;
use Shopware\Core\Content\Media\Thumbnail\ThumbnailStruct;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\EntityRepository;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Shopware\Core\Framework\ORM\Write\FieldException\WriteStackException;
use Shopware\Core\Framework\Struct\StructCollection;
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

    public function testWriteReadMinimalFields()
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

    public function testMimeTypeIsWriteProtected()
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

    public function testThumbnailsIsWriteProtected()
    {
        $mediaId = Uuid::uuid4()->getHex();

        $thumbnailCollection = new StructCollection();
        $thumbnailCollection->add(new ThumbnailStruct());

        $mediaData = [
            'id' => $mediaId,
            'name' => 'test_media',
            'thumbnails' => $thumbnailCollection,
        ];

        $this->expectException(WriteStackException::class);
        $this->repository->create([$mediaData], $this->context);
    }

    public function testThumbnailsArePersistedAsJson()
    {
        $mediaId = Uuid::uuid4()->getHex();

        $thumbnail = new ThumbnailStruct();
        $thumbnail->setHeight(200);
        $thumbnail->setWidth(200);
        $thumbnail->setHighDpi(false);

        $thumbnailCollection = new StructCollection([$thumbnail]);

        $mediaData = [
            'id' => $mediaId,
            'name' => 'test_media',
            'thumbnails' => $thumbnailCollection,
        ];

        $this->context->getExtension('write_protection')->set('write_thumbnails', true);
        $this->repository->create([$mediaData], $this->context);

        $stmt = $this->connection->executeQuery('SELECT `thumbnails` from `media` WHERE id = ?', [Uuid::fromHexToBytes($mediaId)]);
        $thumbnailsFromDb = $stmt->fetchColumn();

        static::assertEquals(json_encode($thumbnailCollection), $thumbnailsFromDb);
    }

    public function testThumbnailsAreConvertedToStructWhenFetchedFromDb()
    {
        $mediaId = Uuid::uuid4()->getHex();

        $thumbnail = new ThumbnailStruct();
        $thumbnail->setHeight(200);
        $thumbnail->setWidth(200);
        $thumbnail->setHighDpi(false);

        $thumbnailCollection = new StructCollection([$thumbnail]);

        $mediaData = [
            'id' => $mediaId,
            'name' => 'test_media',
            'thumbnails' => $thumbnailCollection,
        ];

        $this->context->getExtension('write_protection')->set('write_thumbnails', true);
        $this->repository->create([$mediaData], $this->context);

        $criteria = $this->getIdCriteria($mediaId);

        $searchResult = $this->repository->search($criteria, $this->context);
        $media = $searchResult->getEntities()->get($mediaId);

        static::assertEquals(StructCollection::class, get_class($media->getThumbnails()));

        $persistedThumbnail = $media->getThumbnails()->first();
        static::assertEquals(ThumbnailStruct::class, get_class($persistedThumbnail));
        static::assertEquals(200, $persistedThumbnail->getWidth());
        static::assertEquals(200, $persistedThumbnail->getHeight());
        static::assertFalse($persistedThumbnail->isHighDpi());
    }

    private function getIdCriteria($mediaId)
    {
        $criteria = new Criteria();
        $criteria->setOffset(0);
        $criteria->setLimit(1);
        $criteria->addFilter(new TermQuery('media.id', $mediaId));

        return $criteria;
    }
}
