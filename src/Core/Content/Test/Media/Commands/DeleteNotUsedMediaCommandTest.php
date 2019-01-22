<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Commands;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Commands\DeleteNotUsedMediaCommand;
use Shopware\Core\Content\Media\MediaProtectionFlags;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Content\Test\Media\MediaFixtures;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\Console\Tester\CommandTester;

class DeleteNotUsedMediaCommandTest extends TestCase
{
    use IntegrationTestBehaviour, MediaFixtures;

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

    public function setUp()
    {
        $this->mediaRepository = $this->getContainer()->get('media.repository');

        $this->deleteMediaCommand = $this->getContainer()->get(DeleteNotUsedMediaCommand::class);

        $this->context = Context::createDefaultContext();
        $this->context->getWriteProtection()->allow(MediaProtectionFlags::WRITE_META_INFO);
    }

    public function testExecuteWithoutUnsusedMediaFiles(): void
    {
        $commandTester = new CommandTester($this->deleteMediaCommand);
        $commandTester->execute([]);

        $string = $commandTester->getDisplay();
        static::assertInternalType('int', strpos($string, 'No unused media files found.'));
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

        $this->getPublicFilesystem()->putStream($firstPath, fopen(self::FIXTURE_FILE, 'r'));
        $this->getPublicFilesystem()->putStream($secondPath, fopen(self::FIXTURE_FILE, 'r'));
        $this->getPublicFilesystem()->putStream($thirdPath, fopen(self::FIXTURE_FILE, 'r'));
        $this->getPublicFilesystem()->putStream($fourthPath, fopen(self::FIXTURE_FILE, 'r'));

        $commandTester = new CommandTester($this->deleteMediaCommand);
        $commandTester->setInputs(['yes']);
        $commandTester->execute([]);

        $string = $commandTester->getDisplay();
        static::assertInternalType('int', strpos($string, 'Successfully deleted 2 media files.'));

        $result = $this->mediaRepository->read(
            new Criteria([
                $txt->getId(),
                $png->getId(),
                $withProduct->getId(),
                $withManufacturer->getId(),
            ]), $this->context
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

        $this->getPublicFilesystem()->putStream($firstPath, fopen(self::FIXTURE_FILE, 'r'));
        $this->getPublicFilesystem()->putStream($secondPath, fopen(self::FIXTURE_FILE, 'r'));
        $this->getPublicFilesystem()->putStream($thirdPath, fopen(self::FIXTURE_FILE, 'r'));
        $this->getPublicFilesystem()->putStream($fourthPath, fopen(self::FIXTURE_FILE, 'r'));

        $commandTester = new CommandTester($this->deleteMediaCommand);
        $commandTester->setInputs(['no']);
        $commandTester->execute([]);

        $string = $commandTester->getDisplay();
        static::assertInternalType('int', strpos($string, 'Aborting due to user input.'));

        $result = $this->mediaRepository->read(
            new Criteria([
                $txt->getId(),
                $png->getId(),
                $withProduct->getId(),
                $withManufacturer->getId(),
            ]), $this->context
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
}
