<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Media\File;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Exception\IllegalUrlException;
use Shopware\Core\Content\Media\Exception\MissingFileExtensionException;
use Shopware\Core\Content\Media\Exception\UploadException;
use Shopware\Core\Content\Media\File\FileFetcher;
use Shopware\Core\Content\Media\File\FileUrlValidator;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\TestBootstrapper;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @package content
 *
 * @group needsWebserver
 *
 * @covers \Shopware\Core\Content\Media\File\FileFetcher
 */
class FileFetcherTest extends TestCase
{
    final public const TEST_IMAGE = __DIR__ . '/../../../../../../../src/Core/Content/Test/Media/fixtures/shopware-logo.png';

    private FileFetcher $fileFetcher;

    private bool $mediaDirCreated = false;

    protected function setUp(): void
    {
        $this->fileFetcher = new FileFetcher(new FileUrlValidator());

        $projectDir = (new TestBootstrapper())->getProjectDir();
        if (!\is_dir($projectDir . '/public/media')) {
            mkdir($projectDir . '/public/media');
            $this->mediaDirCreated = true;
        }

        \copy(self::TEST_IMAGE, $projectDir . '/public/media/shopware-logo.png');
    }

    public function tearDown(): void
    {
        $projectDir = (new TestBootstrapper())->getProjectDir();
        \unlink($projectDir . '/public/media/shopware-logo.png');

        if ($this->mediaDirCreated) {
            rmdir($projectDir . '/public/media');
            $this->mediaDirCreated = false;
        }
    }

    public function testFetchRequestData(): void
    {
        $tempFile = (string) tempnam(sys_get_temp_dir(), '');

        $content = fopen(self::TEST_IMAGE, 'rb');
        static::assertIsResource($content);

        $request = new Request([], [], [], [], [], [], $content);
        $request->query->set('extension', 'png');

        $fileSize = filesize(self::TEST_IMAGE);
        $request->headers = new HeaderBag();
        $request->headers->set('content-length', (string) $fileSize);

        try {
            $this->fileFetcher->fetchRequestData(
                $request,
                $tempFile
            );
            $mimeType = mime_content_type($tempFile);

            static::assertEquals('image/png', $mimeType);
            static::assertFileExists($tempFile);
        } finally {
            unlink($tempFile);
        }
    }

    public function testFetchRequestDataWithWrongFileSize(): void
    {
        $this->expectException(UploadException::class);
        $this->expectExceptionMessage('expected content-length did not match actual size');

        $tempFile = (string) tempnam(sys_get_temp_dir(), '');

        $content = fopen(self::TEST_IMAGE, 'rb');
        static::assertIsResource($content);

        $request = new Request([], [], [], [], [], [], $content);
        $request->query->set('extension', 'png');
        $request->headers->set('content-length', '-100');

        $this->fileFetcher->fetchRequestData(
            $request,
            $tempFile
        );
    }

    public function testFetchRequestDataWithMissingExtension(): void
    {
        $this->expectException(MissingFileExtensionException::class);

        $tempFile = (string) tempnam(sys_get_temp_dir(), '');
        $request = new Request();

        $fileSize = filesize(self::TEST_IMAGE);
        $request->headers->set('content-length', (string) $fileSize);

        $this->fileFetcher->fetchRequestData(
            $request,
            $tempFile
        );
    }

    public function testItThrowsExceptionWhenDestinationStreamCannotBeOpened(): void
    {
        $fileName = '';
        $this->expectException(UploadException::class);
        $this->expectExceptionMessage("Could not open Stream to write upload data: {$fileName}");

        $content = fopen(self::TEST_IMAGE, 'rb');
        static::assertIsResource($content);
        $request = new Request([], [], [], [], [], [], $content);
        $request->query->set('extension', 'png');

        $fileSize = filesize(self::TEST_IMAGE);
        $request->headers->set('content-length', (string) $fileSize);

        $this->fileFetcher->fetchRequestData(
            $request,
            $fileName
        );
    }

    public function testFetchFileFromUrl(): void
    {
        $url = EnvironmentHelper::getVariable('APP_URL') . '/media/shopware-logo.png';

        $tempFile = (string) tempnam(sys_get_temp_dir(), '');

        $content = fopen(self::TEST_IMAGE, 'rb');
        static::assertIsResource($content);
        $request = new Request([], [], [], [], [], [], $content);
        $request->query->set('extension', 'png');
        $request->request->set('url', $url);

        $fileFetcher = new FileFetcher(new FileUrlValidator(), true, false);

        try {
            $mediaFile = $fileFetcher->fetchFileFromURL(
                $request,
                $tempFile
            );
            $mimeType = mime_content_type($tempFile);

            $correctMimes = [
                'image/png',
            ];
            static::assertContains($mimeType, $correctMimes);
            static::assertGreaterThan(0, $mediaFile->getFileSize());
            static::assertFileExists($tempFile);
        } finally {
            unlink($tempFile);
        }
    }

    public function testFetchFileFromUrlWithNoUrlGiven(): void
    {
        $this->expectException(UploadException::class);
        $this->expectExceptionMessage('You must provide a valid url.');

        $this->fileFetcher->fetchFileFromURL(
            new Request(),
            'not used in this test'
        );
    }

    public function testFetchFileFromUrlWithMalformedUrl(): void
    {
        $this->expectException(UploadException::class);
        $this->expectExceptionMessage('malformed url');

        $request = new Request();
        $request->query->set('extension', 'png');
        $request->request->set('url', 'ssh://de.shopware.com/press/company/Shopware_Jamaica.jpg');

        $this->fileFetcher->fetchFileFromURL(
            $request,
            'not used in this test'
        );
    }

    /**
     * @group slow
     */
    public function testFetchFileFromUrlWithUnavailableUrl(): void
    {
        $url = 'http://invalid/host';

        $this->expectException(IllegalUrlException::class);

        $request = new Request();
        $request->request->set('url', $url);
        $request->query->set('extension', 'png');

        $this->fileFetcher->fetchFileFromURL(
            $request,
            'not used in this test'
        );
    }

    public function testFetchFileFromUrlWithForbiddenUrl(): void
    {
        $url = 'http://localhost/myForbiddenImage.png';

        $this->expectException(IllegalUrlException::class);

        $request = new Request();
        $request->request->set('url', $url);
        $request->query->set('extension', 'png');

        $this->fileFetcher->fetchFileFromURL(
            $request,
            'not used in this test'
        );
    }

    public function testFetchFileFromUrlWithForbiddenIp4(): void
    {
        $url = 'http://127.0.0.1/myForbiddenImage.png';

        $this->expectException(IllegalUrlException::class);

        $request = new Request();
        $request->request->set('url', $url);
        $request->query->set('extension', 'png');

        $this->fileFetcher->fetchFileFromURL(
            $request,
            'not used in this test'
        );
    }

    public function testFetchFileFromUrlWithForbiddenIp6(): void
    {
        $url = 'http://[::1]/myForbiddenImage.png';

        $this->expectException(IllegalUrlException::class);

        $request = new Request();
        $request->request->set('url', $url);
        $request->query->set('extension', 'png');

        $this->fileFetcher->fetchFileFromURL(
            $request,
            'not used in this test'
        );
    }

    public function testFetchFileFromUrlWithoutLimit(): void
    {
        $url = EnvironmentHelper::getVariable('APP_URL') . '/media/shopware-logo.png';

        $tempFile = (string) tempnam(sys_get_temp_dir(), '');

        $content = fopen(self::TEST_IMAGE, 'rb');
        static::assertIsResource($content);
        $request = new Request([], [], [], [], [], [], $content);
        $request->query->set('extension', 'png');
        $request->request->set('url', $url);

        $fileFetcher = new FileFetcher(new FileUrlValidator(), true, false, 0);

        try {
            $mediaFile = $fileFetcher->fetchFileFromURL(
                $request,
                $tempFile
            );

            $mimeType = mime_content_type($tempFile);

            $correctMimes = [
                'image/png',
            ];
            static::assertContains($mimeType, $correctMimes);
            static::assertGreaterThan(0, $mediaFile->getFileSize());
            static::assertFileExists($tempFile);
        } finally {
            unlink($tempFile);
        }
    }

    public function testFetchFileFromUrlWithLimitInRange(): void
    {
        $url = EnvironmentHelper::getVariable('APP_URL') . '/media/shopware-logo.png';

        $tempFile = (string) tempnam(sys_get_temp_dir(), '');

        $content = fopen(self::TEST_IMAGE, 'rb');
        static::assertIsResource($content);
        $request = new Request([], [], [], [], [], [], $content);
        $request->query->set('extension', 'png');
        $request->request->set('url', $url);

        $fileFetcher = new FileFetcher(new FileUrlValidator(), true, false, 100000);

        try {
            $mediaFile = $fileFetcher->fetchFileFromURL(
                $request,
                $tempFile
            );

            $mimeType = mime_content_type($tempFile);

            $correctMimes = [
                'image/png',
            ];
            static::assertContains($mimeType, $correctMimes);
            static::assertGreaterThan(0, $mediaFile->getFileSize());
            static::assertFileExists($tempFile);
        } finally {
            unlink($tempFile);
        }
    }

    public function testFetchFileFromUrlWithExceedingLimit(): void
    {
        $url = EnvironmentHelper::getVariable('APP_URL') . '/media/shopware-logo.png';

        $tempFile = (string) tempnam(sys_get_temp_dir(), '');

        $content = fopen(self::TEST_IMAGE, 'rb');
        static::assertIsResource($content);
        $request = new Request([], [], [], [], [], [], $content);
        $request->query->set('extension', 'png');
        $request->request->set('url', $url);

        $fileFetcher = new FileFetcher(new FileUrlValidator(), true, false, 5000);

        $this->expectException(UploadException::class);
        $mediaFile = $fileFetcher->fetchFileFromURL($request, $tempFile);
        static::assertEquals(0, $mediaFile->getFileSize());
        static::assertFileDoesNotExist($tempFile);
    }

    public function testUrlUploadLimitDoesNotAffectRequestUpload(): void
    {
        $tempFile = (string) tempnam(sys_get_temp_dir(), '');

        $content = fopen(self::TEST_IMAGE, 'rb');
        static::assertIsResource($content);

        $request = new Request([], [], [], [], [], [], $content);
        $request->query->set('extension', 'png');

        $fileSize = filesize(self::TEST_IMAGE);
        $request->headers = new HeaderBag();
        $request->headers->set('content-length', (string) $fileSize);

        $fileFetcher = new FileFetcher(new FileUrlValidator(), true, true, 10);
        $fileFetcher->fetchRequestData($request, $tempFile);

        static::assertFileExists($tempFile);
        unlink($tempFile);
    }
}
