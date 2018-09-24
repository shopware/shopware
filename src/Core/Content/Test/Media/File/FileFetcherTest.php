<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\File;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Exception\UploadException;
use Shopware\Core\Content\Media\File\FileFetcher;
use Shopware\Core\Content\Media\File\MediaFile;
use Symfony\Component\HttpFoundation\Request;

class FileFetcherTest extends TestCase
{
    public const TEST_IMAGE = __DIR__ . '/../fixtures/shopware-logo.png';

    /**
     * @var FileFetcher
     */
    private $fileFetcher;

    public function SetUp()
    {
        $this->fileFetcher = new FileFetcher();
    }

    public function testFetchRequestData(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), '');
        $request = $this->createMock(Request::class);
        $request->expects(static::once())
            ->method('getContent')
            ->willReturn(fopen(self::TEST_IMAGE, 'r'));

        $fileSize = filesize(self::TEST_IMAGE);

        try {
            $this->fileFetcher->fetchRequestData(
                $request,
                new MediaFile($tempFile, 'image/png', 'png', $fileSize)
            );
            $mimeType = mime_content_type($tempFile);

            static::assertEquals('image/png', $mimeType);
            static::assertTrue(file_exists($tempFile));
        } finally {
            unlink($tempFile);
        }
    }

    public function testFetchRequestDataWithWrongFileSize(): void
    {
        $this->expectException(UploadException::class);
        $this->expectExceptionMessage('expected content-length did not match actual size');

        $tempFile = tempnam(sys_get_temp_dir(), '');
        $request = $this->createMock(Request::class);
        $request->expects(static::once())
            ->method('getContent')
            ->willReturn(fopen(self::TEST_IMAGE, 'r'));

        $this->fileFetcher->fetchRequestData(
            $request,
            new MediaFile($tempFile, 'image/png', 'png', 10)
        );
    }

    public function testFetchFileFromUrl(): void
    {
        $url = 'https://de.shopware.com/press/company/Shopware_Jamaica.jpg';

        $tempFile = tempnam(sys_get_temp_dir(), '');

        try {
            $mediaFile = $this->fileFetcher->fetchFileFromURL(
                new MediaFile($tempFile, 'image/jpeg', 'jpg', 10),
                $url
            );
            $mimeType = mime_content_type($tempFile);

            static::assertEquals('image/jpeg', $mimeType);
            static::assertGreaterThan(0, $mediaFile->getFileSize());
            static::assertTrue(file_exists($tempFile));
        } finally {
            unlink($tempFile);
        }
    }

    public function testFetchFileFromUrlWithMalformedUrl(): void
    {
        $this->expectException(UploadException::class);
        $this->expectExceptionMessage('malformed url');

        $url = 'ssh://de.shopware.com/press/company/Shopware_Jamaica.jpg';
        $tempFile = tempnam(sys_get_temp_dir(), '');

        $this->fileFetcher->fetchFileFromURL(
            new MediaFile($tempFile, 'image/jpeg', 'jpg', 10),
            $url
        );
    }
}
