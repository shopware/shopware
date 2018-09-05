<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Commands;

use Doctrine\DBAL\Connection;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemInterface;
use League\Flysystem\Memory\MemoryAdapter;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailStruct;
use Shopware\Core\Content\Media\Commands\GenerateThumbnailsCommand;
use Shopware\Core\Content\Media\MediaStruct;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Content\Media\Thumbnail\ThumbnailConfiguration;
use Shopware\Core\Content\Media\Thumbnail\ThumbnailService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\CommandTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

class GenerateThumbnailsCommandTest extends TestCase
{
    use IntegrationTestBehaviour, CommandTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var RepositoryInterface
     */
    private $repository;

    /**
     * @var GenerateThumbnailsCommand
     */
    private $thumbnailCommand;

    /** @var FilesystemInterface */
    private $filesystem;

    /** @var UrlGeneratorInterface */
    private $urlGenerator;

    /** @var ThumbnailService */
    private $thumbnailService;

    /** @var ThumbnailConfiguration */
    private $thumbnailConfiguration;

    /** @var Context */
    private $context;

    /** @var string */
    private $catalogId;

    public function setUp()
    {
        $this->repository = $this->getContainer()->get('media.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->filesystem = new Filesystem(new MemoryAdapter());
        $this->urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);
        $this->thumbnailConfiguration = $this->getContainer()->get(ThumbnailConfiguration::class);
        $this->context = Context::createDefaultContext(Defaults::TENANT_ID);
        $this->thumbnailService = new ThumbnailService($this->repository, $this->filesystem, $this->urlGenerator, $this->thumbnailConfiguration);

        $this->thumbnailCommand = new GenerateThumbnailsCommand(
            $this->thumbnailService,
            $this->repository
        );

        $this->createNewCatalog();
        $this->context->getExtension('write_protection')->set('write_media', true);
    }

    public function testExecuteHappyPath()
    {
        $this->createValidMediaFiles();

        $input = new StringInput(sprintf('--tenant-id %s -c %s', Defaults::TENANT_ID, $this->catalogId));
        $output = new BufferedOutput();

        $this->runCommand($this->thumbnailCommand, $input, $output);

        $string = $output->fetch();
        $this->assertEquals(1, preg_match('/.*Generated\s*2.*/', $string));
        $this->assertEquals(1, preg_match('/.*Skipped\s*0.*/', $string));

        $expectedNumberOfThumbnails = count($this->thumbnailConfiguration->getThumbnailSizes());
        if ($this->thumbnailConfiguration->isHighDpi()) {
            $expectedNumberOfThumbnails *= 2;
        }

        $searchCriteria = new Criteria();
        $mediaResult = $this->repository->search($searchCriteria, $this->context);
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

    public function testItSkipsNotSupportedMediaTypes()
    {
        $this->createNotSupportedMediaFiles();

        $input = new StringInput(sprintf('--tenant-id %s -c %s', Defaults::TENANT_ID, $this->catalogId));
        $output = new BufferedOutput();

        $this->runCommand($this->thumbnailCommand, $input, $output);

        $string = $output->fetch();
        $this->assertEquals(1, preg_match('/.*Generated\s*1.*/', $string));
        $this->assertEquals(1, preg_match('/.*Skipped\s*1.*/', $string));

        $expectedNumberOfThumbnails = count($this->thumbnailConfiguration->getThumbnailSizes());
        if ($this->thumbnailConfiguration->isHighDpi()) {
            $expectedNumberOfThumbnails *= 2;
        }

        $searchCriteria = new Criteria();
        $mediaResult = $this->repository->search($searchCriteria, $this->context);
        /** @var MediaStruct $updatedMedia */
        foreach ($mediaResult->getEntities() as $updatedMedia) {
            if (substr($updatedMedia->getMimeType(), 0, 6) === 'image') {
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
            $thumbnail->getHeight(),
            false
        );
        static::assertTrue($this->filesystem->has($thumbnailPath));

        if ($thumbnail->getHighDpi()) {
            $thumbnailPath = $this->urlGenerator->getRelativeThumbnailUrl(
                $mediaId,
                $extension,
                $thumbnail->getWidth(),
                $thumbnail->getHeight(),
                true
            );
            static::assertTrue($this->filesystem->has($thumbnailPath));
        }
    }

    protected function createValidMediaFiles(): void
    {
        $media = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'test_media',
            'mimeType' => 'image/png',
            'fileExtension' => 'png',
            'catalogId' => $this->catalogId,
        ];

        $this->repository->create([$media], $this->context);
        $filePath = $this->urlGenerator->getRelativeMediaUrl($media['id'], 'png');
        $this->filesystem->putStream(
            $filePath,
            fopen(__DIR__ . '/../fixtures/shopware-logo.png', 'r')
        );

        $media = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'test_media2',
            'mimeType' => 'image/jpg',
            'fileExtension' => 'jpg',
            'catalogId' => $this->catalogId,
        ];

        $this->repository->create([$media], $this->context);
        $filePath = $this->urlGenerator->getRelativeMediaUrl($media['id'], 'jpg');
        $this->filesystem->putStream($filePath, fopen(__DIR__ . '/../fixtures/shopware.jpg', 'r'));
    }

    protected function createNotSupportedMediaFiles(): void
    {
        $media = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'test_media',
            'mimeType' => 'application/pdf',
            'fileExtension' => 'pdf',
            'catalogId' => $this->catalogId,
        ];

        $this->repository->create([$media], $this->context);
        $filePath = $this->urlGenerator->getRelativeMediaUrl($media['id'], 'pdf');
        $this->filesystem->putStream(
            $filePath,
            fopen(__DIR__ . '/../fixtures/Shopware_5_3_Broschuere.pdf', 'r')
        );

        $media = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'test_media2',
            'mimeType' => 'image/jpg',
            'fileExtension' => 'jpg',
            'catalogId' => $this->catalogId,
        ];

        $this->repository->create([$media], $this->context);
        $filePath = $this->urlGenerator->getRelativeMediaUrl($media['id'], 'jpg');
        $this->filesystem->putStream($filePath, fopen(__DIR__ . '/../fixtures/shopware.jpg', 'r'));
    }

    private function createNewCatalog(): void
    {
        $catalogRepository = $this->getContainer()->get('catalog.repository');
        $this->catalogId = Uuid::uuid4()->getHex();
        $catalogRepository->create([['id' => $this->catalogId, 'name' => 'test catalog']], $this->context);
        $this->context = $this->context->createWithCatalogIds([$this->catalogId]);
    }
}
