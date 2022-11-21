<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Commands;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\Commands\GenerateThumbnailsCommand;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Message\UpdateThumbnailsMessage;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Content\Media\Thumbnail\ThumbnailService;
use Shopware\Core\Content\Test\Media\MediaFixtures;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Test\TestCaseBase\CommandTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
class GenerateThumbnailsCommandTest extends TestCase
{
    use IntegrationTestBehaviour;
    use CommandTestBehaviour;
    use MediaFixtures;

    /**
     * @var EntityRepository
     */
    private $mediaRepository;

    /**
     * @var EntityRepository
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
        static::assertMatchesRegularExpression('/.*Generated\s*2.*/', $string);
        static::assertMatchesRegularExpression('/.*Skipped\s*' . \count($this->initialMediaIds) . '.*/', $string);

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
        static::assertMatchesRegularExpression('/.*Generated\s*2.*/', $string);
        static::assertMatchesRegularExpression('/.*Skipped\s*' . \count($this->initialMediaIds) . '.*/', $string);

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
        static::assertMatchesRegularExpression('/.*Generated\s*1.*/', $string);
        static::assertMatchesRegularExpression('/.*Skipped\s*' . (\count($this->initialMediaIds) + 1) . '.*/', $string);

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

    public function testItCallsUpdateThumbnailsWithStrictArgument(): void
    {
        $this->createValidMediaFiles();
        $newMedia = $this->getNewMediaEntities();

        $input = new StringInput('--strict');
        $output = new BufferedOutput();

        $thumbnailServiceMock = $this->getMockBuilder(ThumbnailService::class)
            ->disableOriginalConstructor()->getMock();

        $thumbnailServiceMock->expects(static::exactly(\count($this->initialMediaIds) + $newMedia->count()))
            ->method('updateThumbnails')
            ->with(static::anything(), $this->context, true);

        $command = new GenerateThumbnailsCommand(
            $thumbnailServiceMock,
            $this->mediaRepository,
            $this->mediaFolderRepository,
            $this->getContainer()->get('messenger.bus.shopware')
        );

        $this->runCommand($command, $input, $output);
    }

    public function testItCallsUpdateThumbnailsWithoutStrictArgument(): void
    {
        $this->createValidMediaFiles();
        $newMedia = $this->getNewMediaEntities();

        $input = new StringInput('');
        $output = new BufferedOutput();

        $thumbnailServiceMock = $this->getMockBuilder(ThumbnailService::class)
            ->disableOriginalConstructor()->getMock();

        $thumbnailServiceMock->expects(static::exactly(\count($this->initialMediaIds) + $newMedia->count()))
            ->method('updateThumbnails')
            ->with(static::anything(), $this->context, false);

        $command = new GenerateThumbnailsCommand(
            $thumbnailServiceMock,
            $this->mediaRepository,
            $this->mediaFolderRepository,
            $this->getContainer()->get('messenger.bus.shopware')
        );

        $this->runCommand($command, $input, $output);
    }

    public function testItDispatchesUpdateThumbnailsMessageWithCorrectStrictProperty(): void
    {
        $this->createValidMediaFiles();
        $newMedia = $this->getNewMediaEntities();

        $output = new BufferedOutput();

        $affectedMediaIds = array_merge(array_combine($this->initialMediaIds, $this->initialMediaIds), $newMedia->getIds());

        $expectedMessageStrict = new UpdateThumbnailsMessage();
        $expectedMessageStrict->withContext($this->context);
        $expectedMessageStrict->setIsStrict(true);
        $expectedMessageStrict->setMediaIds($affectedMediaIds);

        $expectedMessageNonStrict = new UpdateThumbnailsMessage();
        $expectedMessageNonStrict->withContext($this->context);
        $expectedMessageNonStrict->setIsStrict(false);
        $expectedMessageNonStrict->setMediaIds($affectedMediaIds);

        $messageBusMock = $this->getMockBuilder(MessageBusInterface::class)
            ->disableOriginalConstructor()->getMock();
        $messageBusMock->expects(static::exactly(4))->method('dispatch')
            ->withConsecutive(
                [$expectedMessageStrict, static::anything()],
                [$expectedMessageNonStrict, static::anything()],
                [$expectedMessageNonStrict, static::anything()],
                [$expectedMessageStrict, static::anything()],
            )
            ->willReturnCallback(function ($m, $s) {
                return Envelope::wrap($m, $s);
            });

        $command = new GenerateThumbnailsCommand(
            $this->getContainer()->get(ThumbnailService::class),
            $this->mediaRepository,
            $this->mediaFolderRepository,
            $messageBusMock,
        );

        $this->runCommand($command, new StringInput('--strict --async'), $output);
        $this->runCommand($command, new StringInput('--async'), $output);
        $this->runCommand($command, new StringInput('--async'), $output);
        $this->runCommand($command, new StringInput('--strict --async'), $output);
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
            fopen(__DIR__ . '/../fixtures/small.pdf', 'rb')
        );

        $filePath = $this->urlGenerator->getRelativeMediaUrl($mediaJpg);
        $this->getPublicFilesystem()->putStream($filePath, fopen(__DIR__ . '/../fixtures/shopware.jpg', 'rb'));
    }

    private function getNewMediaEntities(): EntitySearchResult
    {
        if (!empty($this->initialMediaIds)) {
            $criteria = new Criteria($this->initialMediaIds);
            $result = $this->mediaRepository->searchIds($criteria, $this->context);
            static::assertEquals(\count($this->initialMediaIds), $result->getTotal());
        }

        $criteria = new Criteria();
        $criteria->addAssociation('thumbnails');
        if (!empty($this->initialMediaIds)) {
            $criteria->addFilter(new NotFilter(
                NotFilter::CONNECTION_AND,
                [
                    new EqualsAnyFilter('id', $this->initialMediaIds),
                ]
            ));
        }

        return $this->mediaRepository->search($criteria, $this->context);
    }
}
