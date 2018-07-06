<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Upload;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Exception\UploadException;
use Shopware\Core\Content\Media\Upload\FileFetcher;
use Symfony\Component\HttpFoundation\Request;

class FileFetcherTest extends TestCase
{
    const TEST_IMAGE = __DIR__ . '/../fixtures/shopware-logo.png';

    /**
     * @var FileFetcher
     */
    private $fileFetcher;

    public function SetUp()
    {
        $this->fileFetcher = new FileFetcher();
    }

    public function testFetchRequestData()
    {
        $tempFile = tempnam(sys_get_temp_dir(), '');
        $request = $this->createMock(Request::class);
        $request->expects($this->once())
            ->method('getContent')
            ->willReturn(fopen(self::TEST_IMAGE, 'r'));

        $fileSize = filesize(self::TEST_IMAGE);

        try {
            $this->fileFetcher->fetchRequestData($request, $tempFile, 'image/png', $fileSize);
            $mimeType = mime_content_type($tempFile);

            $this->assertEquals('image/png', $mimeType);
            $this->assertTrue(file_exists($tempFile));
        } finally {
            unlink($tempFile);
        }
    }

    public function testFetchRequestDataWithWrongMimeType()
    {
        $this->expectException(UploadException::class);
        $this->expectExceptionMessage('mime-type did not match received data');

        $tempFile = tempnam(sys_get_temp_dir(), '');
        $request = $this->createMock(Request::class);
        $request->expects($this->once())
            ->method('getContent')
            ->willReturn(fopen(self::TEST_IMAGE, 'r'));

        $fileSize = filesize(self::TEST_IMAGE);

        $this->fileFetcher->fetchRequestData($request, $tempFile, 'image/jpeg', $fileSize);
    }

    public function testFetchRequestDataWithWrongFileSize()
    {
        $this->expectException(UploadException::class);
        $this->expectExceptionMessage('expected content-length did not match actual size');

        $tempFile = tempnam(sys_get_temp_dir(), '');
        $request = $this->createMock(Request::class);
        $request->expects($this->once())
            ->method('getContent')
            ->willReturn(fopen(self::TEST_IMAGE, 'r'));

        $this->fileFetcher->fetchRequestData($request, $tempFile, 'image/png', 10);
    }

    public function testFetchFileFromUrl()
    {
        $url = 'https://de.shopware.com/press/company/Shopware_Jamaica.jpg';

        $tempFile = tempnam(sys_get_temp_dir(), '');

        try {
            $writtenBytes = $this->fileFetcher->fetchFileFromURL($tempFile, $url);
            $mimeType = mime_content_type($tempFile);

            $this->assertEquals('image/jpeg', $mimeType);
            $this->assertGreaterThan(0, $writtenBytes);
            $this->assertTrue(file_exists($tempFile));
        } finally {
            unlink($tempFile);
        }
    }

    public function testFetchFileFromUrlWithMalformedUrl()
    {
        $this->expectException(UploadException::class);
        $this->expectExceptionMessage('malformed url');

        $url = 'ssh://de.shopware.com/press/company/Shopware_Jamaica.jpg';
        $tempFile = tempnam(sys_get_temp_dir(), '');

        $this->fileFetcher->fetchFileFromURL($tempFile, $url);
    }
}
