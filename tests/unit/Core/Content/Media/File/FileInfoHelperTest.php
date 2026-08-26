<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\File;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\File\FileInfoHelper;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(FileInfoHelper::class)]
class FileInfoHelperTest extends TestCase
{
    private const MIME_TYPE_FOR_UNDETECTED_FORMATS = 'application/octet-stream';

    public function testGetMimeTypeWithDetectableTypeByFileContentWillDetectByContent(): void
    {
        static::assertSame('image/png', FileInfoHelper::getMimeType(__DIR__ . '/_fixtures/image1x1.png', 'glb'));
    }

    public function testGetMimeTypeWithNotDetectableTypeByFileContentWillDetectByExtension(): void
    {
        static::assertSame('model/gltf-binary', FileInfoHelper::getMimeType(__DIR__ . '/_fixtures/binary', 'glb'));
    }

    public function testGetMimeTypeWithNotDetectableTypeByFileContentAndByExtensionWillReturnCommonType(): void
    {
        static::assertSame('application/octet-stream', FileInfoHelper::getMimeType(__DIR__ . '/_fixtures/binary'));
    }

    #[DataProvider('validMimeTypesProvider')]
    public function testGetExtensionWithValidMimeType(string $mimeType, string $expectedExtension): void
    {
        static::assertSame($expectedExtension, FileInfoHelper::getExtension($mimeType));
    }

    public static function validMimeTypesProvider(): \Generator
    {
        yield 'valid mime-type' => ['image/png', 'png'];
        yield 'FileInfoHelper::MIME_TYPE_FOR_UNDETECTED_FORMATS' => [self::MIME_TYPE_FOR_UNDETECTED_FORMATS, 'bin'];
    }

    public function testGetExtensionWithInvalidMimeTypeThrowsException(): void
    {
        $this->expectExceptionObject(MediaException::invalidMimeType('invalid/mime-type'));
        static::assertSame('bin', FileInfoHelper::getExtension('invalid/mime-type'));
    }

    #[DataProvider('addCharsetProvider')]
    public function testAddCharset(string $mimeType, string $expected): void
    {
        static::assertSame($expected, FileInfoHelper::addCharset($mimeType));
    }

    public static function addCharsetProvider(): \Generator
    {
        yield 'text/plain gets charset' => ['text/plain', 'text/plain; charset=utf-8'];
        yield 'text/csv gets charset' => ['text/csv', 'text/csv; charset=utf-8'];
        yield 'application/json gets charset' => ['application/json', 'application/json; charset=utf-8'];
        yield 'application/xml gets charset' => ['application/xml', 'application/xml; charset=utf-8'];
        yield 'binary stays bare' => ['image/png', 'image/png'];
        yield 'octet-stream stays bare' => ['application/octet-stream', 'application/octet-stream'];
    }

    #[DataProvider('stripParametersProvider')]
    public function testStripParameters(string $contentType, string $expected): void
    {
        static::assertSame($expected, FileInfoHelper::stripParameters($contentType));
    }

    public static function stripParametersProvider(): \Generator
    {
        yield 'strips charset' => ['text/plain; charset=utf-8', 'text/plain'];
        yield 'strips charset without space' => ['text/plain;charset=utf-8', 'text/plain'];
        yield 'leaves bare type unchanged' => ['image/png', 'image/png'];
    }
}
