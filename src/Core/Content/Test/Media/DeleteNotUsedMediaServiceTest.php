<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\DeleteNotUsedMediaService;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;

/**
 * @deprecated tag:v6.6.0 - Will be removed with \Shopware\Core\Content\Media\DeleteNotUsedMediaService
 *
 * @internal
 */
class DeleteNotUsedMediaServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use MediaFixtures;
    use QueueTestBehaviour;

    private const FIXTURE_FILE = __DIR__ . '/fixtures/shopware-logo.png';

    private DeleteNotUsedMediaService $deleteMediaService;

    private EntityRepository $mediaRepo;

    private Context $context;

    protected function setUp(): void
    {
        Feature::skipTestIfActive('v6.6.0.0', $this);

        $this->mediaRepo = $this->getContainer()->get('media.repository');

        $this->context = Context::createDefaultContext();

        $this->deleteMediaService = new DeleteNotUsedMediaService(
            $this->mediaRepo,
            $this->getContainer()->get('media_default_folder.repository')
        );
    }

    public function testCountNotUsedMedia(): void
    {
        $this->setFixtureContext($this->context);

        $this->getTxt();
        $this->getPngWithoutExtension();
        $this->getMediaWithProduct();
        $this->getMediaWithManufacturer();

        static::assertEquals(2, $this->deleteMediaService->countNotUsedMedia($this->context));
    }

    public function testDeleteNotUsedMedia(): void
    {
        $this->setFixtureContext($this->context);

        $txt = $this->getTxt();
        $png = $this->getPng();
        $withProduct = $this->getMediaWithProduct();
        $withManufacturer = $this->getMediaWithManufacturer();

        $urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);
        $firstPath = $urlGenerator->getRelativeMediaUrl($txt);
        $secondPath = $urlGenerator->getRelativeMediaUrl($png);
        $thirdPath = $urlGenerator->getRelativeMediaUrl($withProduct);
        $fourthPath = $urlGenerator->getRelativeMediaUrl($withManufacturer);

        $this->getPublicFilesystem()->writeStream($firstPath, fopen(self::FIXTURE_FILE, 'rb'));
        $this->getPublicFilesystem()->writeStream($secondPath, fopen(self::FIXTURE_FILE, 'rb'));
        $this->getPublicFilesystem()->writeStream($thirdPath, fopen(self::FIXTURE_FILE, 'rb'));
        $this->getPublicFilesystem()->writeStream($fourthPath, fopen(self::FIXTURE_FILE, 'rb'));

        $this->deleteMediaService->deleteNotUsedMedia($this->context);
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
}
