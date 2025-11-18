<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Media;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
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
#[CoversClass(UnusedMediaPurger::class)]
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
