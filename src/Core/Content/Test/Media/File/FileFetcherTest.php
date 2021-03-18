<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\File;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Exception\IllegalUrlException;
use Shopware\Core\Content\Media\Exception\MissingFileExtensionException;
use Shopware\Core\Content\Media\Exception\UploadException;
use Shopware\Core\Content\Media\File\FileFetcher;
use Shopware\Core\Content\Media\File\FileUrlValidator;
use Shopware\Core\Content\Media\File\FileUrlValidatorInterface;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

class FileFetcherTest extends TestCase
{
    public const TEST_IMAGE = __DIR__ . '/../fixtures/shopware-logo.png';

    /**
     * @var FileFetcher
     */
    private $fileFetcher;

    protected function setUp(): void
    {
        $this->fileFetcher = new FileFetcher(new FileUrlValidator());
    }

    public function testFetchRequestData(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), '');
        $request = $this->createMock(Request::class);
        $request->expects(static::once())
            ->method('getContent')
            ->willReturn(fopen(self::TEST_IMAGE, 'rb'));

        $request->query = new ParameterBag([
            'extension' => 'png',
        ]);

        $fileSize = filesize(self::TEST_IMAGE);
        $request->headers = new HeaderBag();
        $request->headers->set('content-length', $fileSize);

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

        $tempFile = tempnam(sys_get_temp_dir(), '');
        $request = $this->createMock(Request::class);
        $request->expects(static::once())
            ->method('getContent')
            ->willReturn(fopen(self::TEST_IMAGE, 'rb'));

        $request->query = new ParameterBag([
            'extension' => 'png',
        ]);

        $request->headers = new HeaderBag();
        $request->headers->set('content-length', -100);

        $this->fileFetcher->fetchRequestData(
            $request,
            $tempFile
        );
    }

    public function testFetchRequestDataWithMissingExtension(): void
    {
        $this->expectException(MissingFileExtensionException::class);

        $tempFile = tempnam(sys_get_temp_dir(), '');
        $request = $this->createMock(Request::class);

        $request->query = new ParameterBag();

        $fileSize = filesize(self::TEST_IMAGE);
        $request->headers = new HeaderBag();
        $request->headers->set('content-length', $fileSize);

        $this->fileFetcher->fetchRequestData(
            $request,
            $tempFile
        );
    }

    public function testItThrowsExceptionWhenDestinationStreamCannotBeOpened(): void
    {
        $fileName = '';
        $this->expectException(UploadException::class);
        $this->expectExceptionMessage("Could not open Stream to write upload data: ${fileName}");

        $request = $this->createMock(Request::class);
        $request->expects(static::once())
            ->method('getContent')
            ->willReturn(fopen(self::TEST_IMAGE, 'rb'));

        $request->query = new ParameterBag([
            'extension' => 'png',
        ]);

        $fileSize = filesize(self::TEST_IMAGE);
        $request->headers = new HeaderBag();
        $request->headers->set('content-length', $fileSize);

        $this->fileFetcher->fetchRequestData(
            $request,
            $fileName
        );
    }

    public function testFetchFileFromUrl(): void
    {
        $url = 'http://assets.shopware.com/sw_logo_white.png';

        $tempFile = tempnam(sys_get_temp_dir(), '');
        $request = $this->createMock(Request::class);
        $request->query = new ParameterBag([
            'extension' => 'png',
        ]);

        $request->request = new ParameterBag([
            'url' => $url,
        ]);

        try {
            $mediaFile = $this->fileFetcher->fetchFileFromURL(
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

        $request = $this->createMock(Request::class);
        $request->request = new ParameterBag();

        $this->fileFetcher->fetchFileFromURL(
            $request,
            'not used in this test'
        );
    }

    public function testFetchFileFromUrlWithMalformedUrl(): void
    {
        $this->expectException(UploadException::class);
        $this->expectExceptionMessage('malformed url');

        $request = $this->createMock(Request::class);

        $request->query = new ParameterBag([
            'extension' => 'png',
        ]);
        $request->request = new ParameterBag([
            'url' => 'ssh://de.shopware.com/press/company/Shopware_Jamaica.jpg',
        ]);

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

        $request = $this->createMock(Request::class);
        $request->request = new ParameterBag([
            'url' => $url,
        ]);
        $request->query = new ParameterBag([
            'extension' => 'png',
        ]);

        $this->fileFetcher->fetchFileFromURL(
            $request,
            'not used in this test'
        );
    }

    public function testFetchFileFromUrlWithForbiddenUrl(): void
    {
        $url = 'http://localhost/myForbiddenImage.png';

        $this->expectException(IllegalUrlException::class);

        $request = $this->createMock(Request::class);
        $request->request = new ParameterBag([
            'url' => $url,
        ]);
        $request->query = new ParameterBag([
            'extension' => 'png',
        ]);

        $this->fileFetcher->fetchFileFromURL(
            $request,
            'not used in this test'
        );
    }

    public function testFetchFileFromUrlWithForbiddenIp4(): void
    {
        $url = 'http://127.0.0.1/myForbiddenImage.png';

        $this->expectException(IllegalUrlException::class);

        $request = $this->createMock(Request::class);
        $request->request = new ParameterBag([
            'url' => $url,
        ]);
        $request->query = new ParameterBag([
            'extension' => 'png',
        ]);

        $this->fileFetcher->fetchFileFromURL(
            $request,
            'not used in this test'
        );
    }

    public function testFetchFileFromUrlWithForbiddenIp6(): void
    {
        $url = 'http://[::1]/myForbiddenImage.png';

        $this->expectException(IllegalUrlException::class);

        $request = $this->createMock(Request::class);
        $request->request = new ParameterBag([
            'url' => $url,
        ]);
        $request->query = new ParameterBag([
            'extension' => 'png',
        ]);

        $this->fileFetcher->fetchFileFromURL(
            $request,
            'not used in this test'
        );
    }

    /**
     * @group needsWebserver
     */
    public function testFetchFileDoesNotRedirect(): void
    {
        static::markTestSkipped();

        $appUrl = trim($_SERVER['APP_URL'] ?? '');
        if ($appUrl === '') {
            static::markTestSkipped('APP_URL not defined');
        }

        $fileFetcher = new FileFetcher(
            $this->createMock(FileUrlValidatorInterface::class),
            true,
            false
        );

        $query = [
            'a' => Uuid::randomHex(),
            'b' => 'test',
        ];

        $tmpFileName = tempnam(sys_get_temp_dir(), 'testFetchFileDoesNotRedirect');
        $queryString = http_build_query($query);

        $url = sprintf('%s/api/_action/redirect-to-echo?%s', $appUrl, $queryString);

        $fileFetcher->fetchFileFromURL(
            new Request(['extension' => 'json'], ['url' => $url]),
            $tmpFileName
        );

        $responseContent = file_get_contents($tmpFileName);
        unlink($tmpFileName);

        static::assertSame('', $responseContent);
    }
}
