<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Media;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Event\UnusedMediaSearchEvent;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\UnusedMediaPurger;
use Shopware\Core\Content\Test\Media\MediaFixtures;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
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
            new EventDispatcher()
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
        );

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
        );

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
        );

        $deleted = $purger->deleteNotUsedMedia(gracePeriodDays: 1);
        $this->runWorker();

        static::assertSame(0, $deleted);

        $stillExisting = $this->mediaRepo
            ->search(new Criteria([$txt->getId()]), $this->context)
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
        );

        static::assertNotNull($result->get($usedByA11yDocument->getId()));
        static::assertNull($result->get($unusedMedia->getId()));

        static::assertTrue($this->getPublicFilesystem()->has($usedPath));
        static::assertFalse($this->getPublicFilesystem()->has($unusedPath));
    }
}
