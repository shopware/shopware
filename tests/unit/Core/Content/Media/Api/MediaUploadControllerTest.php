<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Api\MediaUploadController;
use Shopware\Core\Content\Media\File\FileNameProvider;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MediaUploadController::class)]
class MediaUploadControllerTest extends TestCase
{
    /**
     * @var list<string>
     */
    public static array $createdTempFiles = [];

    private FileSaver&MockObject $fileSaver;

    private MediaService&MockObject $mediaService;

    private FileNameProvider&MockObject $fileNameProvider;

    private ResponseFactoryInterface&MockObject $responseFactory;

    protected function setUp(): void
    {
        self::$createdTempFiles = [];
        $this->fileSaver = $this->createMock(FileSaver::class);
        $this->mediaService = $this->createMock(MediaService::class);
        $this->fileNameProvider = $this->createMock(FileNameProvider::class);
        $this->responseFactory = $this->createMock(ResponseFactoryInterface::class);
    }

    protected function tearDown(): void
    {
        foreach (self::$createdTempFiles as $tempFile) {
            if (\is_file($tempFile)) {
                \unlink($tempFile);
            }
        }
    }

    public function testRemoveNonPrintingCharactersInFileNameBeforeUpload(): void
    {
        $invalidFileName = 'file­name.png';
        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $request = new Request(['fileName' => $invalidFileName]);

        $uploadFile = new MediaFile(
            '/tmp/foo/bar/baz',
            'image/png',
            'png',
            1000,
            Uuid::randomHex()
        );

        $this->mediaService->expects($this->once())
            ->method('fetchFile')
            ->willReturn($uploadFile);

        $this->fileSaver->expects($this->once())
            ->method('persistFileToMedia')
            ->with($uploadFile, 'filename.png', $mediaId, $context);

        $mediaUploadController = new MediaUploadController(
            $this->mediaService,
            $this->fileSaver,
            $this->fileNameProvider,
            new MediaDefinition(),
            new EventDispatcher()
        );

        $mediaUploadController->upload($request, $mediaId, $context, $this->responseFactory);
    }

    public function testRemoveNonPrintingCharactersInFileNameBeforeRename(): void
    {
        $invalidFileName = 'file­name.png';
        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $request = new Request([], ['fileName' => $invalidFileName]);

        $this->fileSaver->expects($this->once())
            ->method('renameMedia')
            ->with($mediaId, 'filename.png', $context);

        $mediaUploadController = new MediaUploadController(
            $this->mediaService,
            $this->fileSaver,
            $this->fileNameProvider,
            new MediaDefinition(),
            new EventDispatcher()
        );

        $mediaUploadController->renameMediaFile($request, $mediaId, $context, $this->responseFactory);
    }

    public function testRemoveNonPrintingCharactersInFileNameBeforeProvideName(): void
    {
        $invalidFileName = 'file­name.png';
        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $request = new Request([
            'fileName' => $invalidFileName,
            'extension' => 'jpg',
            'mediaId' => $mediaId,
        ]);

        $this->fileNameProvider->expects($this->once())
            ->method('provide')
            ->with('filename.png', 'jpg', $mediaId, $context);

        $mediaUploadController = new MediaUploadController(
            $this->mediaService,
            $this->fileSaver,
            $this->fileNameProvider,
            new MediaDefinition(),
            new EventDispatcher()
        );

        $mediaUploadController->provideName($request, $context);
    }

    #[DataProvider('nonAsciiFileNameProvider')]
    public function testKeepsNonAsciiCharactersInFileNameBeforeUpload(string $fileName): void
    {
        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $uploadFile = new MediaFile('/tmp/foo/bar/baz', 'image/jpeg', 'jpg', 1000, Uuid::randomHex());

        $this->mediaService->method('fetchFile')->willReturn($uploadFile);

        $this->fileSaver->expects($this->once())
            ->method('persistFileToMedia')
            ->with($uploadFile, $fileName, $mediaId, $context);

        $this->createController()->upload(new Request(['fileName' => $fileName]), $mediaId, $context, $this->responseFactory);
    }

    #[DataProvider('nonAsciiFileNameProvider')]
    public function testKeepsNonAsciiCharactersInFileNameBeforeRename(string $fileName): void
    {
        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $this->fileSaver->expects($this->once())
            ->method('renameMedia')
            ->with($mediaId, $fileName, $context);

        $this->createController()->renameMediaFile(new Request([], ['fileName' => $fileName]), $mediaId, $context, $this->responseFactory);
    }

    #[DataProvider('nonAsciiFileNameProvider')]
    public function testKeepsNonAsciiCharactersInFileNameBeforeProvideName(string $fileName): void
    {
        $context = Context::createDefaultContext();

        $this->fileNameProvider->expects($this->once())
            ->method('provide')
            ->with($fileName, 'jpg', null, $context);

        $this->createController()->provideName(new Request(['fileName' => $fileName, 'extension' => 'jpg']), $context);
    }

    public static function nonAsciiFileNameProvider(): \Generator
    {
        yield 'lowercase umlaut' => ['Erdmännchen'];
        yield 'uppercase umlauts and sharp s' => ['Ärmel Öl Übung ß'];
        yield 'accented latin' => ['Crème brûlée'];
        yield 'cyrillic' => ['Тест'];
        yield 'multi byte' => ['テスト'];
        yield 'emoji' => ['Föö🚀bár'];
    }

    public function testStripsZeroWidthAndBidiFormatCharactersInFileName(): void
    {
        $context = Context::createDefaultContext();

        $this->fileNameProvider->expects($this->once())
            ->method('provide')
            ->with('Fööbár', 'jpg', null, $context);

        $this->createController()->provideName(
            new Request(['fileName' => "Föö\u{200B}b\u{202E}ár", 'extension' => 'jpg']),
            $context
        );
    }

    public function testUploadDoesNotLeaveTempFileOnInvalidUtf8FileName(): void
    {
        try {
            $this->createController()->upload(new Request(['fileName' => "\xFF\xFE"]), Uuid::randomHex(), Context::createDefaultContext(), $this->responseFactory);
            static::fail('Expected MediaException for an invalid UTF-8 file name');
        } catch (MediaException) {
        }

        static::assertSame([], array_values(array_filter(self::$createdTempFiles, 'is_file')));
    }

    public function testUploadThrowsOnInvalidUtf8FileName(): void
    {
        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('is not permitted');

        $this->createController()->upload(new Request(['fileName' => "\xFF\xFE"]), Uuid::randomHex(), Context::createDefaultContext(), $this->responseFactory);
    }

    public function testRenameThrowsOnInvalidUtf8FileName(): void
    {
        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('is not permitted');

        $this->createController()->renameMediaFile(new Request([], ['fileName' => "\xFF\xFE"]), Uuid::randomHex(), Context::createDefaultContext(), $this->responseFactory);
    }

    public function testProvideNameThrowsOnInvalidUtf8FileName(): void
    {
        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('is not permitted');

        $this->createController()->provideName(new Request(['fileName' => "\xFF\xFE", 'extension' => 'jpg']), Context::createDefaultContext());
    }

    private function createController(): MediaUploadController
    {
        return new MediaUploadController(
            $this->mediaService,
            $this->fileSaver,
            $this->fileNameProvider,
            new MediaDefinition(),
            new EventDispatcher()
        );
    }
}

namespace Shopware\Core\Content\Media\Api;

use Shopware\Tests\Unit\Core\Content\Media\Api\MediaUploadControllerTest;

function tempnam(string $dir, string $prefix): string|false
{
    $tempFile = \tempnam($dir, $prefix);

    if (\is_string($tempFile)) {
        MediaUploadControllerTest::$createdTempFiles[] = $tempFile;
    }

    return $tempFile;
}
