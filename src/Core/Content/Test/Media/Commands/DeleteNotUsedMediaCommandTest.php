<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Commands;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Commands\DeleteNotUsedMediaCommand;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Content\Test\Media\MediaFixtures;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
class DeleteNotUsedMediaCommandTest extends TestCase
{
    use IntegrationTestBehaviour;
    use MediaFixtures;
    use QueueTestBehaviour;

    private const FIXTURE_FILE = __DIR__ . '/../fixtures/shopware-logo.png';

    /**
     * @var EntityRepositoryInterface
     */
    private $mediaRepository;

    /**
     * @var DeleteNotUsedMediaCommand
     */
    private $deleteMediaCommand;

    /**
     * @var Context
     */
    private $context;

    protected function setUp(): void
    {
        $this->mediaRepository = $this->getContainer()->get('media.repository');

        $this->deleteMediaCommand = $this->getContainer()->get(DeleteNotUsedMediaCommand::class);

        $this->context = Context::createDefaultContext();
    }

    public function testExecuteWithoutUnusedMediaFiles(): void
    {
        $commandTester = new CommandTester($this->deleteMediaCommand);
        $commandTester->execute([]);

        $string = $commandTester->getDisplay();
        static::assertIsInt(mb_strpos($string, 'No unused media files found.'));
    }

    public function testExecuteWithConfirm(): void
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

        $resource = fopen(self::FIXTURE_FILE, 'rb');
        static::assertIsResource($resource);
        $this->getPublicFilesystem()->putStream($firstPath, $resource);
        $this->getPublicFilesystem()->putStream($secondPath, $resource);
        $this->getPublicFilesystem()->putStream($thirdPath, $resource);
        $this->getPublicFilesystem()->putStream($fourthPath, $resource);

        $commandTester = new CommandTester($this->deleteMediaCommand);
        $commandTester->setInputs(['yes']);
        $commandTester->execute([]);

        $string = $commandTester->getDisplay();

        static::assertIsInt(mb_strpos($string, 'Successfully deleted 2 media files.'));

        $this->runWorker();

        $result = $this->mediaRepository->search(
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

    public function testExecuteWithOutConfirm(): void
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

        $resource = fopen(self::FIXTURE_FILE, 'rb');
        static::assertIsResource($resource);
        $this->getPublicFilesystem()->putStream($firstPath, $resource);
        $this->getPublicFilesystem()->putStream($secondPath, $resource);
        $this->getPublicFilesystem()->putStream($thirdPath, $resource);
        $this->getPublicFilesystem()->putStream($fourthPath, $resource);

        $commandTester = new CommandTester($this->deleteMediaCommand);
        $commandTester->setInputs(['no']);
        $commandTester->execute([]);

        $this->runWorker();

        $string = $commandTester->getDisplay();
        static::assertIsInt(mb_strpos($string, 'Aborting due to user input.'));

        $result = $this->mediaRepository->search(
            new Criteria([
                $txt->getId(),
                $png->getId(),
                $withProduct->getId(),
                $withManufacturer->getId(),
            ]),
            $this->context
        );

        static::assertNotNull($result->get($txt->getId()));
        static::assertNotNull($result->get($png->getId()));
        static::assertNotNull($result->get($withProduct->getId()));
        static::assertNotNull($result->get($withManufacturer->getId()));

        static::assertTrue($this->getPublicFilesystem()->has($firstPath));
        static::assertTrue($this->getPublicFilesystem()->has($secondPath));
        static::assertTrue($this->getPublicFilesystem()->has($thirdPath));
        static::assertTrue($this->getPublicFilesystem()->has($fourthPath));
    }

    public function testExecuteWithFolderEntityRestriction(): void
    {
        $this->setFixtureContext($this->context);

        $this->addFolderToFixture('MediaWithProduct', 'product');
        $this->addFolderToFixture('MediaWithManufacturer', 'product_manufacturer');

        $withProduct = $this->getMediaWithProduct();
        $withManufacturer = $this->getMediaWithManufacturer();

        $this->getContainer()->get('product.repository')->delete([
            ['id' => $this->mediaFixtures['MediaWithProduct']['productMedia'][0]['product']['id']],
        ], $this->context);
        $this->getContainer()->get('product_manufacturer.repository')->delete([
            ['id' => $this->mediaFixtures['MediaWithManufacturer']['productManufacturers'][0]['id']],
        ], $this->context);

        $urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);
        $firstPath = $urlGenerator->getRelativeMediaUrl($withProduct);
        $secondPath = $urlGenerator->getRelativeMediaUrl($withManufacturer);

        $resource = fopen(self::FIXTURE_FILE, 'rb');
        static::assertIsResource($resource);
        $this->getPublicFilesystem()->putStream($firstPath, $resource);
        $this->getPublicFilesystem()->putStream($secondPath, $resource);

        $commandTester = new CommandTester($this->deleteMediaCommand);
        $commandTester->setInputs(['yes']);
        $commandTester->execute(['--folder-entity' => 'product']);

        $string = $commandTester->getDisplay();

        static::assertIsInt(mb_strpos($string, 'Successfully deleted 1 media files.'));

        $this->runWorker();

        $result = $this->mediaRepository->search(
            new Criteria([
                $withProduct->getId(),
                $withManufacturer->getId(),
            ]),
            $this->context
        );

        static::assertNull($result->get($withProduct->getId()));
        static::assertNotNull($result->get($withManufacturer->getId()));

        static::assertFalse($this->getPublicFilesystem()->has($firstPath));
        static::assertTrue($this->getPublicFilesystem()->has($secondPath));
    }

    private function addFolderToFixture(string $fixture, string $defaultFolderEntity): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('defaultFolder.entity', $defaultFolderEntity));

        $mediaFolderId = $this->getContainer()->get('media_folder.repository')
            ->searchIds($criteria, $this->context)
            ->firstId();

        $this->mediaFixtures[$fixture]['mediaFolder'] = [
            'id' => $mediaFolderId,
        ];
    }
}
