<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Commands;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\Commands\GenerateThumbnailsCommand;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Content\Test\Media\MediaFixtures;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Test\TestCaseBase\CommandTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

class GenerateThumbnailsCommandTest extends TestCase
{
    use IntegrationTestBehaviour;
    use CommandTestBehaviour;
    use MediaFixtures;

    /**
     * @var EntityRepositoryInterface
     */
    private $mediaRepository;

    /**
     * @var EntityRepositoryInterface
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

    /**
     * @var array
     */
    private $initialMediaIds;

    protected function setUp(): void
    {
        $this->mediaRepository = $this->getContainer()->get('media.repository');
        $this->mediaFolderRepository = $this->getContainer()->get('media_folder.repository');
        $this->urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);
        $this->thumbnailCommand = $this->getContainer()->get(GenerateThumbnailsCommand::class);
        $this->context = Context::createDefaultContext();

        $this->initialMediaIds = $this->mediaRepository->searchIds(new Criteria(), $this->context)->getIds();
    }

    public function testExecuteHappyPath(): void
    {
        $this->createValidMediaFiles();

        $input = new StringInput('');
        $output = new BufferedOutput();

        $this->runCommand($this->thumbnailCommand, $input, $output);

        $string = $output->fetch();
        static::assertRegExp('/.*Generated\s*2.*/', $string);
        static::assertRegExp('/.*Skipped\s*' . \count($this->initialMediaIds) . '.*/', $string);

        $mediaResult = $this->getNewMediaEntities();
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

    public function testExecuteWithCustomLimit(): void
    {
        $this->createValidMediaFiles();

        $input = new StringInput('-b 2');
        $output = new BufferedOutput();

        $this->runCommand($this->thumbnailCommand, $input, $output);

        $string = $output->fetch();
        static::assertRegExp('/.*Generated\s*2.*/', $string);
        static::assertRegExp('/.*Skipped\s*' . \count($this->initialMediaIds) . '.*/', $string);

        $mediaResult = $this->getNewMediaEntities();
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
        static::assertRegExp('/.*Generated\s*1.*/', $string);
        static::assertRegExp('/.*Skipped\s*' . (\count($this->initialMediaIds) + 1) . '.*/', $string);

        $mediaResult = $this->getNewMediaEntities();
        /** @var MediaEntity $updatedMedia */
        foreach ($mediaResult->getEntities() as $updatedMedia) {
            if (mb_strpos($updatedMedia->getMimeType(), 'image') === 0) {
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

    public function testHappyPathWithGivenFolderName(): void
    {
        $this->createValidMediaFiles();

        $input = new StringInput('--folder-name="test folder"');
        $output = new BufferedOutput();

        $this->runCommand($this->thumbnailCommand, $input, $output);

        $mediaResult = $this->getNewMediaEntities();
        /** @var MediaEntity $updatedMedia */
        foreach ($mediaResult->getEntities() as $updatedMedia) {
            $thumbnails = $updatedMedia->getThumbnails();
            static::assertEquals(2, $thumbnails->count());

            foreach ($thumbnails as $thumbnail) {
                $this->assertThumbnailExists($updatedMedia, $thumbnail);
            }
        }
    }

    public function testSkipsMediaEntitiesFromDifferentFolders(): void
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

        $mediaResult = $this->getNewMediaEntities();
        foreach ($mediaResult->getEntities() as $updatedMedia) {
            $thumbnails = $updatedMedia->getThumbnails();
            static::assertEquals(0, $thumbnails->count());
        }
    }

    public function testCommandAbortsIfNoFolderCanBeFound(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Could not find a folder with the name: "non-existing-folder"');

        $input = new StringInput('--folder-name="non-existing-folder"');
        $output = new BufferedOutput();
        $this->runCommand($this->thumbnailCommand, $input, $output);
    }

    public function testItThrowsExceptionOnNonNumericLimit(): void
    {
        $this->expectException(\Exception::class);
        $input = new StringInput('-i test');
        $output = new BufferedOutput();

        $this->runCommand($this->thumbnailCommand, $input, $output);
    }

    protected function assertThumbnailExists(MediaEntity $media, MediaThumbnailEntity $thumbnail): void
    {
        $thumbnailPath = $this->urlGenerator->getRelativeThumbnailUrl(
            $media,
            $thumbnail
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
            fopen(__DIR__ . '/../fixtures/shopware-logo.png', 'rb')
        );

        $filePath = $this->urlGenerator->getRelativeMediaUrl($mediaJpg);
        $this->getPublicFilesystem()->putStream(
            $filePath,
            fopen(__DIR__ . '/../fixtures/shopware.jpg', 'rb')
        );
    }

    protected function createNotSupportedMediaFiles(): void
    {
        $this->setFixtureContext($this->context);
        $mediaPdf = $this->getPdf();
        $mediaJpg = $this->getJpgWithFolder();

        $this->mediaRepository->update([
            [
                'id' => $mediaPdf->getId(),
                'mediaFolderId' => $mediaJpg->getMediaFolderId(),
            ],
        ], $this->context);

        $filePath = $this->urlGenerator->getRelativeMediaUrl($mediaPdf);
        $this->getPublicFilesystem()->putStream(
            $filePath,
            fopen(__DIR__ . '/../fixtures/Shopware_5_3_Broschuere.pdf', 'rb')
        );

        $filePath = $this->urlGenerator->getRelativeMediaUrl($mediaJpg);
        $this->getPublicFilesystem()->putStream($filePath, fopen(__DIR__ . '/../fixtures/shopware.jpg', 'rb'));
    }

    private function getNewMediaEntities()
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', $this->initialMediaIds));
        $result = $this->mediaRepository->searchIds($criteria, $this->context);
        static::assertEquals(\count($this->initialMediaIds), $result->getTotal());

        $criteria = new Criteria();
        $criteria->addAssociation('thumbnails');
        $criteria->addFilter(new NotFilter(
            NotFilter::CONNECTION_AND,
            [
                new EqualsAnyFilter('id', $this->initialMediaIds),
            ]
        ));

        return $this->mediaRepository->search($criteria, $this->context);
    }
}
