<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Commands;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\Commands\GenerateThumbnailsCommand;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
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
    use IntegrationTestBehaviour,
        CommandTestBehaviour,
        MediaFixtures;

    /**
     * @var RepositoryInterface
     */
    private $mediaRepository;

    /**
     * @var RepositoryInterface
     */
    private $mediaFolderRepository;

    /**
     * @var GenerateThumbnailsCommand
     */
    private $thumbnailCommand;

    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    /**
     * @var Context
     */
    private $context;

    public function setUp()
    {
        $this->mediaRepository = $this->getContainer()->get('media.repository');
        $this->mediaFolderRepository = $this->getContainer()->get('media_folder.repository');
        $this->urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);
        $this->thumbnailCommand = $this->getContainer()->get(GenerateThumbnailsCommand::class);
        $this->context = $this->getContextWithWriteAccess();
    }

    public function testExecuteHappyPath(): void
    {
        $this->createValidMediaFiles();

        $input = new StringInput('');
        $output = new BufferedOutput();

        $this->runCommand($this->thumbnailCommand, $input, $output);

        $string = $output->fetch();
        static::assertEquals(1, preg_match('/.*Generated\s*2.*/', $string));
        static::assertEquals(1, preg_match('/.*Skipped\s*0.*/', $string));

        $mediaResult = $this->mediaRepository->search(new Criteria(), $this->context);
        /** @var MediaEntity $updatedMedia */
        foreach ($mediaResult->getEntities() as $updatedMedia) {
            $thumbnails = $updatedMedia->getThumbnails();
            static::assertEquals(
                2,
                $thumbnails->count()
            );

            foreach ($thumbnails as $thumbnail) {
                $this->assertThumbnailExists($updatedMedia, $thumbnail);
            }
        }
    }

    public function testExecuteWithCustomBatchSize(): void
    {
        $this->createValidMediaFiles();

        $input = new StringInput('-b 1');
        $output = new BufferedOutput();

        $this->runCommand($this->thumbnailCommand, $input, $output);

        $string = $output->fetch();
        static::assertEquals(1, preg_match('/.*Generated\s*2.*/', $string));
        static::assertEquals(1, preg_match('/.*Skipped\s*0.*/', $string));

        $searchCriteria = new Criteria();
        $mediaResult = $this->mediaRepository->search($searchCriteria, $this->context);
        /** @var MediaEntity $updatedMedia */
        foreach ($mediaResult->getEntities() as $updatedMedia) {
            $thumbnails = $updatedMedia->getThumbnails();
            static::assertEquals(
                2,
                $thumbnails->count()
            );

            foreach ($thumbnails as $thumbnail) {
                $this->assertThumbnailExists($updatedMedia, $thumbnail);
            }
        }
    }

    public function testItSkipsNotSupportedMediaTypes(): void
    {
        $this->createNotSupportedMediaFiles();

        $input = new StringInput('');
        $output = new BufferedOutput();

        $this->runCommand($this->thumbnailCommand, $input, $output);

        $string = $output->fetch();
        static::assertEquals(1, preg_match('/.*Generated\s*1.*/', $string));
        static::assertEquals(1, preg_match('/.*Skipped\s*1.*/', $string));

        $mediaResult = $this->mediaRepository->search(new Criteria(), $this->context);
        /** @var MediaEntity $updatedMedia */
        foreach ($mediaResult->getEntities() as $updatedMedia) {
            if (strpos($updatedMedia->getMimeType(), 'image') === 0) {
                $thumbnails = $updatedMedia->getThumbnails();
                static::assertEquals(
                    2,
                    $thumbnails->count()
                );

                foreach ($thumbnails as $thumbnail) {
                    $this->assertThumbnailExists($updatedMedia, $thumbnail);
                }
            }
        }
    }

    public function testHappyPathWithGivenFolderName()
    {
        $this->createValidMediaFiles();

        $input = new StringInput('--folder-name="test folder"');
        $output = new BufferedOutput();

        $this->runCommand($this->thumbnailCommand, $input, $output);

        $mediaResult = $this->mediaRepository->search(new Criteria(), $this->context);
        /** @var MediaEntity $updatedMedia */
        foreach ($mediaResult->getEntities() as $updatedMedia) {
            $thumbnails = $updatedMedia->getThumbnails();
            static::assertEquals(2, $thumbnails->count());

            foreach ($thumbnails as $thumbnail) {
                $this->assertThumbnailExists($updatedMedia, $thumbnail);
            }
        }
    }

    public function testSkipsMediaEntitiesFromDifferentFolders()
    {
        $this->createValidMediaFiles();
        $this->mediaFolderRepository->create([
            [
                'name' => 'folder-to-search',
                'useParentConfiguration' => false,
                'configuration' => [],
            ],
        ], $this->context);

        $input = new StringInput('--folder-name="folder-to-search"');
        $output = new BufferedOutput();

        $this->runCommand($this->thumbnailCommand, $input, $output);

        $mediaResult = $this->mediaRepository->search(new Criteria(), $this->context);
        foreach ($mediaResult->getEntities() as $updatedMedia) {
            $thumbnails = $updatedMedia->getThumbnails();
            static::assertEquals(0, $thumbnails->count());
        }
    }

    public function testCommandAbortsIfNoFolderCanBeFound()
    {
        static::expectException(\UnexpectedValueException::class);
        static::expectExceptionMessage('Could not find a folder with the name: "non-existing-folder"');

        $input = new StringInput('--folder-name="non-existing-folder"');
        $output = new BufferedOutput();
        $this->runCommand($this->thumbnailCommand, $input, $output);
    }

    public function testItThrowsExceptionOnNonNumericBatchsize(): void
    {
        static::expectException(\Exception::class);
        $input = new StringInput('-b test');
        $output = new BufferedOutput();

        $this->runCommand($this->thumbnailCommand, $input, $output);
    }

    protected function assertThumbnailExists(MediaEntity $media, MediaThumbnailEntity $thumbnail): void
    {
        $thumbnailPath = $this->urlGenerator->getRelativeThumbnailUrl(
            $media,
            $thumbnail->getWidth(),
            $thumbnail->getHeight()
        );
        static::assertTrue($this->getPublicFilesystem()->has($thumbnailPath));
    }

    protected function createValidMediaFiles(): void
    {
        $this->setFixtureContext($this->context);
        $mediaPng = $this->getPngWithFolder();
        $mediaJpg = $this->getJpgWithFolder();

        $filePath = $this->urlGenerator->getRelativeMediaUrl($mediaPng);
        $this->getPublicFilesystem()->putStream(
            $filePath,
            fopen(__DIR__ . '/../fixtures/shopware-logo.png', 'r')
        );

        $filePath = $this->urlGenerator->getRelativeMediaUrl($mediaJpg);
        $this->getPublicFilesystem()->putStream(
            $filePath,
            fopen(__DIR__ . '/../fixtures/shopware.jpg', 'r')
        );
    }

    protected function createNotSupportedMediaFiles(): void
    {
        $this->setFixtureContext($this->context);
        $mediaPdf = $this->getPdf();
        $mediaJpg = $this->getJpgWithFolder();

        $filePath = $this->urlGenerator->getRelativeMediaUrl($mediaPdf);
        $this->getPublicFilesystem()->putStream(
            $filePath,
            fopen(__DIR__ . '/../fixtures/Shopware_5_3_Broschuere.pdf', 'r')
        );

        $filePath = $this->urlGenerator->getRelativeMediaUrl($mediaJpg);
        $this->getPublicFilesystem()->putStream($filePath, fopen(__DIR__ . '/../fixtures/shopware.jpg', 'r'));
    }
}
