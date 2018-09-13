<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\File;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaProtectionFlags;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class FileSaverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public const TEST_IMAGE = __DIR__ . '/../fixtures/shopware-logo.png';

    /**
     * @var RepositoryInterface
     */
    private $repository;

    /**
     * @var FileSaver
     */
    private $fileSaver;

    /** @var UrlGeneratorInterface */
    private $urlGenerator;

    public function setUp()
    {
        $this->repository = $this->getContainer()->get('media.repository');
        $this->urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);
        $this->fileSaver = $this->getContainer()->get(FileSaver::class);
    }

    public function test_PersistFileToMedia_happyPathForInitialUpload(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), '');
        copy(self::TEST_IMAGE, $tempFile);

        $fileSize = filesize($tempFile);
        $mediaFile = new MediaFile($tempFile, 'image/png', 'png', $fileSize);

        $mediaId = Uuid::uuid4()->getHex();

        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        $context->getWriteProtection()->allow(MediaProtectionFlags::WRITE_META_INFO);

        $this->repository->create(
            [
                [
                    'id' => $mediaId,
                    'name' => 'test file',
                ],
            ],
            $context
        );

        try {
            $this->fileSaver->persistFileToMedia(
                $mediaFile,
                $mediaId,
                $context
            );
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }

        $path = $this->urlGenerator->getRelativeMediaUrl($mediaId, 'png');
        static::assertTrue($this->getPublicFilesystem()->has($path));
    }

    public function test_persistFileToMedia_removesOldFile()
    {
        $tempFile = tempnam(sys_get_temp_dir(), '');
        copy(self::TEST_IMAGE, $tempFile);

        $fileSize = filesize($tempFile);
        $mediaFile = new MediaFile($tempFile, 'image/png', 'png', $fileSize);

        $mediaId = Uuid::uuid4()->getHex();

        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        $context->getExtension('write_protection')->set('write_media', true);

        $this->repository->create(
            [
                [
                    'id' => $mediaId,
                    'name' => 'test file',
                    'mimeType' => 'plain/txt',
                    'fileExtension' => 'txt',
                    'hasFile' => true,
                ],
            ],
            $context
        );
        $oldMediaFilePath = $this->urlGenerator->getRelativeMediaUrl($mediaId, 'txt');
        $this->getPublicFilesystem()->put($oldMediaFilePath, 'Some ');

        try {
            $this->fileSaver->persistFileToMedia(
                $mediaFile,
                $mediaId,
                $context
            );
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }

        $path = $this->urlGenerator->getRelativeMediaUrl($mediaId, 'png');
        static::assertTrue($this->getPublicFilesystem()->has($path));
        static::assertFalse($this->getPublicFilesystem()->has($oldMediaFilePath));
    }
}
