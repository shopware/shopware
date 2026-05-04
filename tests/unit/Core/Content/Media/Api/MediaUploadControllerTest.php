<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Api;

use PHPUnit\Framework\Attributes\CoversClass;
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
    public static bool $simulateFailedTempnam = false;

    private FileSaver&MockObject $fileSaver;

    private MediaService&MockObject $mediaService;

    private FileNameProvider&MockObject $fileNameProvider;

    private ResponseFactoryInterface&MockObject $responseFactory;

    protected function setUp(): void
    {
        $this->fileSaver = $this->createMock(FileSaver::class);
        $this->mediaService = $this->createMock(MediaService::class);
        $this->fileNameProvider = $this->createMock(FileNameProvider::class);
        $this->responseFactory = $this->createMock(ResponseFactoryInterface::class);
    }

    protected function tearDown(): void
    {
        self::$simulateFailedTempnam = false;
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

    public function testRenameThrowsWhenEmptyFileName(): void
    {
        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $request = new Request([], ['fileName' => '']);

        static::expectException(MediaException::class);
        static::expectExceptionMessage('A valid filename must be provided.');

        $controller = new MediaUploadController(
            $this->mediaService,
            $this->fileSaver,
            $this->fileNameProvider,
            new MediaDefinition(),
            new EventDispatcher()
        );

        $controller->renameMediaFile($request, $mediaId, $context, $this->responseFactory);
    }

    public function testProvideNameThrowsWhenEmptyFileName(): void
    {
        $context = Context::createDefaultContext();
        $request = new Request(['fileName' => '', 'extension' => 'jpg']);

        static::expectException(MediaException::class);
        static::expectExceptionMessage('A valid filename must be provided.');

        $controller = new MediaUploadController(
            $this->mediaService,
            $this->fileSaver,
            $this->fileNameProvider,
            new MediaDefinition(),
            new EventDispatcher()
        );

        $controller->provideName($request, $context);
    }

    public function testProvideNameThrowsWhenMissingExtension(): void
    {
        $context = Context::createDefaultContext();
        $request = new Request(['fileName' => 'test', 'extension' => '']);

        static::expectException(MediaException::class);
        static::expectExceptionMessage('No file extension provided. Please use the "extension" query parameter to specify the extension of the uploaded file.');

        $controller = new MediaUploadController(
            $this->mediaService,
            $this->fileSaver,
            $this->fileNameProvider,
            new MediaDefinition(),
            new EventDispatcher()
        );

        $controller->provideName($request, $context);
    }

    public function testUploadThrowsWhenTempFileCannotBeCreated(): void
    {
        self::$simulateFailedTempnam = true;

        static::expectException(MediaException::class);
        static::expectExceptionMessage('Cannot create a temp file.');

        $controller = new MediaUploadController(
            $this->mediaService,
            $this->fileSaver,
            $this->fileNameProvider,
            new MediaDefinition(),
            new EventDispatcher()
        );

        $controller->upload(new Request(), Uuid::randomHex(), Context::createDefaultContext(), $this->responseFactory);
    }

    public function testUploadThrowsOnIllegalFileName(): void
    {
        static::expectException(MediaException::class);
        static::expectExceptionMessage('is not permitted');

        $controller = new MediaUploadController(
            $this->mediaService,
            $this->fileSaver,
            $this->fileNameProvider,
            new MediaDefinition(),
            new EventDispatcher()
        );

        $controller->upload(new Request(['fileName' => "\xFF\xFE"]), Uuid::randomHex(), Context::createDefaultContext(), $this->responseFactory);
    }

    public function testRenameThrowsOnIllegalFileName(): void
    {
        static::expectException(MediaException::class);
        static::expectExceptionMessage('is not permitted');

        $controller = new MediaUploadController(
            $this->mediaService,
            $this->fileSaver,
            $this->fileNameProvider,
            new MediaDefinition(),
            new EventDispatcher()
        );

        $controller->renameMediaFile(new Request([], ['fileName' => "\xFF\xFE"]), Uuid::randomHex(), Context::createDefaultContext(), $this->responseFactory);
    }

    public function testProvideNameThrowsOnIllegalFileName(): void
    {
        static::expectException(MediaException::class);
        static::expectExceptionMessage('is not permitted');

        $controller = new MediaUploadController(
            $this->mediaService,
            $this->fileSaver,
            $this->fileNameProvider,
            new MediaDefinition(),
            new EventDispatcher()
        );

        $controller->provideName(new Request(['fileName' => "\xFF\xFE", 'extension' => 'png']), Context::createDefaultContext());
    }
}

namespace Shopware\Core\Content\Media\Api;

use Shopware\Tests\Unit\Core\Content\Media\Api\MediaUploadControllerTest;

function tempnam(string $dir, string $prefix): string|false
{
    if (MediaUploadControllerTest::$simulateFailedTempnam) {
        return false;
    }

    return \tempnam($dir, $prefix);
}
