<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\File;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\File\FileFetcher;
use Shopware\Core\Content\Media\File\FileLoader;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Test\Media\MediaFixtures;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
final class FileLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use MediaFixtures;

    public const TEST_IMAGE = __DIR__ . '/../fixtures/shopware-logo.png';

    private FileLoader $fileLoader;

    private FileFetcher $fileFetcher;

    private FileSaver $fileSaver;

    private EntityRepositoryInterface $mediaRepository;

    protected function setUp(): void
    {
        $this->fileLoader = $this->getContainer()->get(FileLoader::class);
        $this->fileFetcher = $this->getContainer()->get(FileFetcher::class);
        $this->fileSaver = $this->getContainer()->get(FileSaver::class);
    }

    public function testLoadMediaFile(): void
    {
        $context = Context::createDefaultContext();
        $blob = \file_get_contents(self::TEST_IMAGE);
        $mediaFile = $this->fileFetcher->fetchBlob($blob, 'png', 'image/png');
        $mediaId = Uuid::randomHex();
        $this->fileSaver->persistFileToMedia($mediaFile, $mediaId . '.png', $mediaId, $context);

        static::assertSame($blob, $this->fileLoader->loadMediaFile($mediaId, $context));

        $this->mediaRepository->delete([$mediaId], $context);
    }

    public function testLoadMediaFileStream(): void
    {
        $context = Context::createDefaultContext();
        $blob = \file_get_contents(self::TEST_IMAGE);
        $mediaFile = $this->fileFetcher->fetchBlob($blob, 'png', 'image/png');
        $mediaId = Uuid::randomHex();
        $this->fileSaver->persistFileToMedia($mediaFile, $mediaId . '.png', $mediaId, $context);

        static::assertSame($blob, (string) $this->fileLoader->loadMediaFileStream($mediaId, $context));

        $this->mediaRepository->delete([$mediaId], $context);
    }
}
