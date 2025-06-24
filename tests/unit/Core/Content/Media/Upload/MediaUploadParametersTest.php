<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Upload;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\Upload\MediaUploadParameters;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MediaUploadParameters::class)]
class MediaUploadParametersTest extends TestCase
{
    public function testConstruct(): void
    {
        $id = 'test-id';
        $mediaFolderId = 'folder-id';
        $private = true;
        $fileName = 'test-file.jpg';
        $mimeType = 'image/jpeg';
        $deduplicate = false;

        $parameters = new MediaUploadParameters(
            id: $id,
            mediaFolderId: $mediaFolderId,
            private: $private,
            fileName: $fileName,
            mimeType: $mimeType,
            deduplicate: $deduplicate
        );

        static::assertSame($id, $parameters->id);
        static::assertSame($mediaFolderId, $parameters->mediaFolderId);
        static::assertSame($private, $parameters->private);
        static::assertSame($fileName, $parameters->fileName);
        static::assertSame($mimeType, $parameters->mimeType);
        static::assertSame($deduplicate, $parameters->deduplicate);
    }

    public function testConstructWithDefaults(): void
    {
        $parameters = new MediaUploadParameters();

        static::assertNull($parameters->id);
        static::assertNull($parameters->mediaFolderId);
        static::assertNull($parameters->private);
        static::assertNull($parameters->fileName);
        static::assertNull($parameters->mimeType);
        static::assertNull($parameters->deduplicate);
    }

    public function testFillDefaultFileNameWithExistingFileName(): void
    {
        $existingFileName = 'existing.jpg';
        $parameters = new MediaUploadParameters(fileName: $existingFileName);

        $parameters->fillDefaultFileName('new-file.jpg');

        static::assertSame($existingFileName, $parameters->fileName);
    }

    public function testFillDefaultFileNameWithNullFileName(): void
    {
        $parameters = new MediaUploadParameters();
        $newFileName = 'new-file.jpg';

        $parameters->fillDefaultFileName($newFileName);

        static::assertSame($newFileName, $parameters->fileName);
    }

    public function testGetFileNameWithoutExtensionSuccess(): void
    {
        $parameters = new MediaUploadParameters(fileName: 'test-file.jpg');

        $result = $parameters->getFileNameWithoutExtension();

        static::assertSame('test-file', $result);
    }

    public function testGetFileNameWithoutExtensionWithMultipleDots(): void
    {
        $parameters = new MediaUploadParameters(fileName: 'test.file.name.jpg');

        $result = $parameters->getFileNameWithoutExtension();

        static::assertSame('test.file.name', $result);
    }

    public function testGetFileNameWithoutExtensionWithNoExtension(): void
    {
        $parameters = new MediaUploadParameters(fileName: 'test-file');

        $result = $parameters->getFileNameWithoutExtension();

        static::assertSame('test-fil', $result);
    }

    public function testGetFileNameWithoutExtensionThrowsExceptionWhenFileNameIsNull(): void
    {
        $parameters = new MediaUploadParameters();

        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('A valid filename must be provided.');

        $parameters->getFileNameWithoutExtension();
    }

    public function testGetFileNameExtensionSuccess(): void
    {
        $parameters = new MediaUploadParameters(fileName: 'test-file.jpg');

        $result = $parameters->getFileNameExtension();

        static::assertSame('jpg', $result);
    }

    public function testGetFileNameExtensionWithMultipleDots(): void
    {
        $parameters = new MediaUploadParameters(fileName: 'test.file.name.png');

        $result = $parameters->getFileNameExtension();

        static::assertSame('png', $result);
    }

    public function testGetFileNameExtensionWithNoExtension(): void
    {
        $parameters = new MediaUploadParameters(fileName: 'test-file');

        $result = $parameters->getFileNameExtension();

        static::assertSame('', $result);
    }

    public function testGetFileNameExtensionWithUppercaseExtension(): void
    {
        $parameters = new MediaUploadParameters(fileName: 'test-file.JPG');

        $result = $parameters->getFileNameExtension();

        static::assertSame('JPG', $result);
    }

    public function testGetFileNameExtensionThrowsExceptionWhenFileNameIsNull(): void
    {
        $parameters = new MediaUploadParameters();

        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('A valid filename must be provided.');

        $parameters->getFileNameExtension();
    }

    public function testGetFileNameWithoutExtensionWithSpecialCharacters(): void
    {
        $parameters = new MediaUploadParameters(fileName: 'test-file-äöü.jpg');

        $result = $parameters->getFileNameWithoutExtension();

        static::assertSame('test-file-äöü', $result);
    }

    public function testGetFileNameWithoutExtensionWithEmptyString(): void
    {
        $parameters = new MediaUploadParameters(fileName: '');

        $result = $parameters->getFileNameWithoutExtension();

        static::assertSame('', $result);
    }

    public function testGetFileNameExtensionWithDotOnly(): void
    {
        $parameters = new MediaUploadParameters(fileName: '.');

        $result = $parameters->getFileNameExtension();

        static::assertSame('', $result);
    }

    public function testGetFileNameExtensionWithHiddenFile(): void
    {
        $parameters = new MediaUploadParameters(fileName: '.htaccess');

        $result = $parameters->getFileNameExtension();

        static::assertSame('htaccess', $result);
    }
}
