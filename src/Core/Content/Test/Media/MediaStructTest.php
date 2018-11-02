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
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\TermQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\WriteStackException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class MediaStructTest extends TestCase
{
    use IntegrationTestBehaviour, MediaFixtures;

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
        $media = $this->getEmptyMedia();

        $criteria = $this->getIdCriteria($media->getId());
        $result = $this->repository->search($criteria, $this->context);
        $media = $result->getEntities()->first();

        static::assertInstanceOf(MediaStruct::class, $media);
        static::assertEquals($media->getId(), $media->getId());
        static::assertEquals('test_media', $media->getName());
    }

    public function testMimeTypeIsWriteProtected(): void
    {
        $this->expectException(WriteStackException::class);
        $this->getPngWithoutExtension();
    }

    public function testThumbnailsIsWriteProtected(): void
    {
        $this->expectException(WriteStackException::class);

        $this->setFixtureContext($this->context);
        $this->getMediaWithThumbnail();
    }

    public function testThumbnailsAreConvertedToStructWhenFetchedFromDb(): void
    {
        $this->context->getWriteProtection()->allow(MediaProtectionFlags::WRITE_THUMBNAILS);

        $this->setFixtureContext($this->context);
        $media = $this->getMediaWithThumbnail();

        $criteria = $this->getIdCriteria($media->getId());
        $searchResult = $this->repository->search($criteria, $this->context);
        $fetchedMedia = $searchResult->getEntities()->get($media->getId());

        static::assertEquals(MediaThumbnailCollection::class, \get_class($fetchedMedia->getThumbnails()));

        $persistedThumbnail = $fetchedMedia->getThumbnails()->first();
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
