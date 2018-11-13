<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Commands;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailStruct;
use Shopware\Core\Content\Media\Commands\GenerateThumbnailsCommand;
use Shopware\Core\Content\Media\MediaStruct;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Content\Media\Thumbnail\ThumbnailConfiguration;
use Shopware\Core\Content\Test\Media\MediaFixtures;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\CommandTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

class GenerateThumbnailsCommandTest extends TestCase
{
    use IntegrationTestBehaviour, CommandTestBehaviour, MediaFixtures;

    /**
     * @var RepositoryInterface
     */
    private $mediaRepository;

    /**
     * @var GenerateThumbnailsCommand
     */
    private $thumbnailCommand;

    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    /**
     * @var ThumbnailConfiguration
     */
    private $thumbnailConfiguration;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var string
     */
    private $catalogId;

    public function setUp()
    {
        $this->mediaRepository = $this->getContainer()->get('media.repository');
        $this->urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);
        $this->thumbnailConfiguration = $this->getContainer()->get(ThumbnailConfiguration::class);

        $this->thumbnailCommand = $this->getContainer()->get(GenerateThumbnailsCommand::class);

        $this->context = $this->getContextWithCatalogAndWriteAccess();
        $this->catalogId = $this->context->getCatalogIds()[0];
    }

    public function testExecuteHappyPath(): void
    {
        $this->createValidMediaFiles();

        $input = new StringInput(sprintf('-c %s', $this->catalogId));
        $output = new BufferedOutput();

        $this->runCommand($this->thumbnailCommand, $input, $output);

        $string = $output->fetch();
        static::assertEquals(1, preg_match('/.*Generated\s*2.*/', $string));
        static::assertEquals(1, preg_match('/.*Skipped\s*0.*/', $string));

        $expectedNumberOfThumbnails = \count($this->thumbnailConfiguration->getThumbnailSizes());
        if ($this->thumbnailConfiguration->isHighDpi()) {
            $expectedNumberOfThumbnails *= 2;
        }

        $searchCriteria = new Criteria();
        $mediaResult = $this->mediaRepository->search($searchCriteria, $this->context);
        /** @var MediaStruct $updatedMedia */
        foreach ($mediaResult->getEntities() as $updatedMedia) {
            $thumbnails = $updatedMedia->getThumbnails();
            static::assertEquals(
                $expectedNumberOfThumbnails,
                $thumbnails->count()
            );

            foreach ($thumbnails as $thumbnail) {
                $this->assertThumbnailExists($updatedMedia->getId(), $updatedMedia->getFileExtension(), $thumbnail);
            }
        }
    }

    public function testItSkipsNotSupportedMediaTypes(): void
    {
        $this->createNotSupportedMediaFiles();

        $input = new StringInput(sprintf('-c %s', $this->catalogId));
        $output = new BufferedOutput();

        $this->runCommand($this->thumbnailCommand, $input, $output);

        $string = $output->fetch();
        static::assertEquals(1, preg_match('/.*Generated\s*1.*/', $string));
        static::assertEquals(1, preg_match('/.*Skipped\s*1.*/', $string));

        $expectedNumberOfThumbnails = \count($this->thumbnailConfiguration->getThumbnailSizes());
        if ($this->thumbnailConfiguration->isHighDpi()) {
            $expectedNumberOfThumbnails *= 2;
        }

        $searchCriteria = new Criteria();
        $mediaResult = $this->mediaRepository->search($searchCriteria, $this->context);
        /** @var MediaStruct $updatedMedia */
        foreach ($mediaResult->getEntities() as $updatedMedia) {
            if (strpos($updatedMedia->getMimeType(), 'image') === 0) {
                $thumbnails = $updatedMedia->getThumbnails();
                static::assertEquals(
                    $expectedNumberOfThumbnails,
                    $thumbnails->count()
                );

                foreach ($thumbnails as $thumbnail) {
                    $this->assertThumbnailExists($updatedMedia->getId(), $updatedMedia->getFileExtension(), $thumbnail);
                }
            }
        }
    }

    protected function assertThumbnailExists(string $mediaId, string $extension, MediaThumbnailStruct $thumbnail): void
    {
        $thumbnailPath = $this->urlGenerator->getRelativeThumbnailUrl(
            $mediaId,
            $extension,
            $thumbnail->getWidth(),
            $thumbnail->getHeight()
        );
        static::assertTrue($this->getPublicFilesystem()->has($thumbnailPath));

        if ($thumbnail->getHighDpi()) {
            $thumbnailPath = $this->urlGenerator->getRelativeThumbnailUrl(
                $mediaId,
                $extension,
                $thumbnail->getWidth(),
                $thumbnail->getHeight(),
                true
            );
            static::assertTrue($this->getPublicFilesystem()->has($thumbnailPath));
        }
    }

    protected function createValidMediaFiles(): void
    {
        $this->setFixtureContext($this->context);
        $mediaPng = $this->getPngInCatalog();
        $mediaJpg = $this->getJpgInCatalog();

        $filePath = $this->urlGenerator->getRelativeMediaUrl($mediaPng->getId(), 'png');
        $this->getPublicFilesystem()->putStream(
            $filePath,
            fopen(__DIR__ . '/../fixtures/shopware-logo.png', 'r')
        );

        $filePath = $this->urlGenerator->getRelativeMediaUrl($mediaJpg->getId(), 'jpg');
        $this->getPublicFilesystem()->putStream(
            $filePath,
            fopen(__DIR__ . '/../fixtures/shopware.jpg', 'r')
        );
    }

    protected function createNotSupportedMediaFiles(): void
    {
        $this->setFixtureContext($this->context);
        $mediaPdf = $this->getPdfInCatalog();
        $mediaJpg = $this->getJpgInCatalog();

        $filePath = $this->urlGenerator->getRelativeMediaUrl($mediaPdf->getId(), 'pdf');
        $this->getPublicFilesystem()->putStream(
            $filePath,
            fopen(__DIR__ . '/../fixtures/Shopware_5_3_Broschuere.pdf', 'r')
        );

        $filePath = $this->urlGenerator->getRelativeMediaUrl($mediaJpg->getId(), 'jpg');
        $this->getPublicFilesystem()->putStream($filePath, fopen(__DIR__ . '/../fixtures/shopware.jpg', 'r'));
    }
}
