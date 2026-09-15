<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Media;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Event\UnusedMediaSearchEvent;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\UnusedMediaPurger;
use Shopware\Core\Content\Product\Aggregate\ProductDownload\ProductDownloadDefinition;
use Shopware\Core\Content\Test\Media\MediaFixtures;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('discovery')]
class UnusedMediaPurgerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use MediaFixtures;
    use QueueTestBehaviour;

    private const FIXTURE_FILE = __DIR__ . '/fixtures/shopware-logo.png';

    private UnusedMediaPurger $unusedMediaPurger;

    /**
     * @var EntityRepository<MediaCollection>
     */
    private EntityRepository $mediaRepo;

    private Context $context;

    protected function setUp(): void
    {
        $mediaRepo = static::getContainer()->get('media.repository');
        static::assertInstanceOf(EntityRepository::class, $mediaRepo);

        $this->mediaRepo = $mediaRepo;
        $this->context = Context::createDefaultContext();

        $this->unusedMediaPurger = new UnusedMediaPurger(
            $this->mediaRepo,
            $this->createMock(Connection::class),
            new EventDispatcher(),
            new NativeClock()
        );
    }

    public function testDeleteNotUsedMedia(): void
    {
        $this->setFixtureContext($this->context);

        $txt = $this->getTxt();
        $png = $this->getPng();
        $withProduct = $this->getMediaWithProduct();
        $withManufacturer = $this->getMediaWithManufacturer();

        $firstPath = $txt->getPath();
        $secondPath = $png->getPath();
        $thirdPath = $withProduct->getPath();
        $fourthPath = $withManufacturer->getPath();

        $this->getPublicFilesystem()->writeStream($firstPath, \fopen(self::FIXTURE_FILE, 'r'));
        $this->getPublicFilesystem()->writeStream($secondPath, \fopen(self::FIXTURE_FILE, 'r'));
        $this->getPublicFilesystem()->writeStream($thirdPath, \fopen(self::FIXTURE_FILE, 'r'));
        $this->getPublicFilesystem()->writeStream($fourthPath, \fopen(self::FIXTURE_FILE, 'r'));

        $this->unusedMediaPurger->deleteNotUsedMedia();
        $this->runWorker();

        $result = $this->mediaRepo->search(
            new Criteria([
                $txt->getId(),
                $png->getId(),
                $withProduct->getId(),
                $withManufacturer->getId(),
            ]),
            $this->context
        )->getEntities();

        static::assertNull($result->get($txt->getId()));
        static::assertNull($result->get($png->getId()));
        static::assertNotNull($result->get($withProduct->getId()));
        static::assertNotNull($result->get($withManufacturer->getId()));

        static::assertFalse($this->getPublicFilesystem()->has($firstPath));
        static::assertFalse($this->getPublicFilesystem()->has($secondPath));
        static::assertTrue($this->getPublicFilesystem()->has($thirdPath));
        static::assertTrue($this->getPublicFilesystem()->has($fourthPath));
    }

    public function testDeleteNotUsedMediaWithLimit(): void
    {
        $this->setFixtureContext($this->context);

        $txt = $this->getTxt();
        $png = $this->getPng();
        $pdf = $this->getPdf();

        $firstPath = $txt->getPath();
        $secondPath = $png->getPath();
        $thirdPath = $pdf->getPath();

        $this->getPublicFilesystem()->writeStream($firstPath, \fopen(self::FIXTURE_FILE, 'r'));
        $this->getPublicFilesystem()->writeStream($secondPath, \fopen(self::FIXTURE_FILE, 'r'));
        $this->getPublicFilesystem()->writeStream($thirdPath, \fopen(self::FIXTURE_FILE, 'r'));

        $this->unusedMediaPurger->deleteNotUsedMedia(limit: 2);
        $this->runWorker();

        $result = $this->mediaRepo->search(
            new Criteria([
                $txt->getId(),
                $png->getId(),
                $pdf->getId(),
            ]),
            $this->context
        )->getEntities();

        static::assertNull($result->get($txt->getId()));
        static::assertNull($result->get($png->getId()));
        static::assertNull($result->get($pdf->getId()));

        static::assertFalse($this->getPublicFilesystem()->has($firstPath));
        static::assertFalse($this->getPublicFilesystem()->has($secondPath));
        static::assertFalse($this->getPublicFilesystem()->has($thirdPath));
    }

    public function testDeleteNotUsedMediaWithGracePeriodHandlesEmptyBatchFromEventListener(): void
    {
        $this->setFixtureContext($this->context);

        $txt = $this->getTxt();
        $this->getPublicFilesystem()->writeStream($txt->getPath(), \fopen(self::FIXTURE_FILE, 'r'));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            UnusedMediaSearchEvent::class,
            static function (UnusedMediaSearchEvent $event): void {
                $event->markAsUsed($event->getUnusedIds());
            }
        );

        $connection = static::getContainer()->get(Connection::class);
        static::assertInstanceOf(Connection::class, $connection);

        $purger = new UnusedMediaPurger(
            $this->mediaRepo,
            $connection,
            $eventDispatcher,
            new NativeClock()
        );

        $deleted = $purger->deleteNotUsedMedia(gracePeriodDays: 1);
        $this->runWorker();

        static::assertSame(0, $deleted);

        $stillExisting = $this->mediaRepo
            ->search(new Criteria([$txt->getId()]), $this->context)->getEntities()
            ->get($txt->getId());
        static::assertNotNull($stillExisting);
    }

    public function testGetNotUsedMediaWithOffsetAndGracePeriodHandlesEmptyBatchFromEventListener(): void
    {
        $this->setFixtureContext($this->context);

        $txt = $this->getTxt();
        $this->getPublicFilesystem()->writeStream($txt->getPath(), \fopen(self::FIXTURE_FILE, 'r'));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            UnusedMediaSearchEvent::class,
            static function (UnusedMediaSearchEvent $event): void {
                $event->markAsUsed($event->getUnusedIds());
            }
        );

        $connection = static::getContainer()->get(Connection::class);
        static::assertInstanceOf(Connection::class, $connection);

        $purger = new UnusedMediaPurger(
            $this->mediaRepo,
            $connection,
            $eventDispatcher,
            new NativeClock()
        );

        $batches = iterator_to_array($purger->getNotUsedMedia(offset: 0, gracePeriodDays: 1), false);

        static::assertSame([[]], $batches);
    }

    public function testGetNotUsedMediaWithOffsetPastEndDoesNotCrash(): void
    {
        $this->setFixtureContext($this->context);

        $connection = static::getContainer()->get(Connection::class);
        static::assertInstanceOf(Connection::class, $connection);

        $purger = new UnusedMediaPurger(
            $this->mediaRepo,
            $connection,
            new EventDispatcher(),
            new NativeClock()
        );

        $batches = iterator_to_array($purger->getNotUsedMedia(offset: 99999, gracePeriodDays: 1), false);

        static::assertSame([[]], $batches);
    }

    public function testDeleteNotUsedMediaDoesNotDeleteA11yDocumentMedia(): void
    {
        $this->setFixtureContext($this->context);

        $usedByA11yDocument = $this->getMediaWithA11yDocument();
        $unusedMedia = $this->getTxt();

        $usedPath = $usedByA11yDocument->getPath();
        $unusedPath = $unusedMedia->getPath();

        $this->getPublicFilesystem()->writeStream($usedPath, \fopen(self::FIXTURE_FILE, 'r'));
        $this->getPublicFilesystem()->writeStream($unusedPath, \fopen(self::FIXTURE_FILE, 'r'));

        $this->unusedMediaPurger->deleteNotUsedMedia();
        $this->runWorker();

        $result = $this->mediaRepo->search(
            new Criteria([
                $usedByA11yDocument->getId(),
                $unusedMedia->getId(),
            ]),
            $this->context
        )->getEntities();

        static::assertNotNull($result->get($usedByA11yDocument->getId()));
        static::assertNull($result->get($unusedMedia->getId()));

        static::assertTrue($this->getPublicFilesystem()->has($usedPath));
        static::assertFalse($this->getPublicFilesystem()->has($unusedPath));
    }

    public function testDeleteNotUsedMediaWithFolderEntityKeepsMediaUsedByAnotherEntity(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        static::assertInstanceOf(Connection::class, $connection);

        $downloadFolderId = $connection->fetchOne(
            'SELECT LOWER(HEX(media_folder.id)) FROM media_default_folder
             INNER JOIN media_folder ON media_default_folder.id = media_folder.default_folder_id
             WHERE entity = :entity',
            ['entity' => ProductDownloadDefinition::ENTITY_NAME]
        );
        static::assertIsString($downloadFolderId);

        $usedByProduct = Uuid::randomHex();
        $unused = Uuid::randomHex();

        // both media live in the product download folder, only one of them is referenced anywhere
        $this->mediaRepo->create([
            $this->mediaPayload($usedByProduct, $downloadFolderId),
            $this->mediaPayload($unused, $downloadFolderId),
        ], $this->context);

        static::getContainer()->get('product.repository')->create([[
            'id' => Uuid::randomHex(),
            'productNumber' => 'product-' . $usedByProduct,
            'name' => 'product using download folder media as image',
            'stock' => 1,
            'tax' => ['name' => 'test tax', 'taxRate' => 19],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'media' => [['mediaId' => $usedByProduct, 'position' => 1]],
        ]], $this->context);

        $purger = new UnusedMediaPurger(
            $this->mediaRepo,
            $connection,
            new EventDispatcher(),
            new NativeClock()
        );

        $purger->deleteNotUsedMedia(folderEntity: ProductDownloadDefinition::ENTITY_NAME);
        $this->runWorker();

        $result = $this->mediaRepo->search(new Criteria([$usedByProduct, $unused]), $this->context)->getEntities();

        static::assertNotNull(
            $result->get($usedByProduct),
            'media inside the product download folder must not be deleted while a product still references it as an image'
        );
        static::assertNull($result->get($unused));
    }

    /**
     * @return array<string, mixed>
     */
    private function mediaPayload(string $id, string $mediaFolderId): array
    {
        return [
            'id' => $id,
            'fileName' => 'media-' . $id,
            'fileExtension' => 'png',
            'mimeType' => 'image/png',
            'fileSize' => 1024,
            'private' => false,
            'mediaFolderId' => $mediaFolderId,
        ];
    }
}
