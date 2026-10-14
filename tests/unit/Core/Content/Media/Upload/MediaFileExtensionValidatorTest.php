<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Upload;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\Upload\MediaFileExtensionListProvider;
use Shopware\Core\Content\Media\Upload\MediaFileExtensionValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MediaFileExtensionValidator::class)]
class MediaFileExtensionValidatorTest extends TestCase
{
    public function testValidateAllowsPublicExtension(): void
    {
        $context = Context::createDefaultContext();
        $mediaFileExtensionListProvider = $this->createMock(MediaFileExtensionListProvider::class);

        $mediaFileExtensionListProvider->expects($this->once())
            ->method('getAllowedExtensions')
            ->with(false, $context)
            ->willReturn(['jpg', 'png']);

        $validator = new MediaFileExtensionValidator($mediaFileExtensionListProvider);
        $validator->validate('jpg', false, $context);
    }

    public function testValidateAllowsPrivateExtension(): void
    {
        $context = Context::createDefaultContext();
        $mediaFileExtensionListProvider = $this->createMock(MediaFileExtensionListProvider::class);

        $mediaFileExtensionListProvider->expects($this->once())
            ->method('getAllowedExtensions')
            ->with(true, $context)
            ->willReturn(['pdf']);

        $validator = new MediaFileExtensionValidator($mediaFileExtensionListProvider);
        $validator->validate('pdf', true, $context);
    }

    public function testValidateIsCaseInsensitive(): void
    {
        $mediaFileExtensionListProvider = static::createStub(MediaFileExtensionListProvider::class);
        $mediaFileExtensionListProvider->method('getAllowedExtensions')->willReturn(['JPG']);

        $validator = new MediaFileExtensionValidator($mediaFileExtensionListProvider);

        $this->expectNotToPerformAssertions();
        $validator->validate('jpg', false, Context::createDefaultContext());
    }

    public function testValidateThrowsWhenExtensionIsNotAllowed(): void
    {
        $mediaFileExtensionListProvider = static::createStub(MediaFileExtensionListProvider::class);
        $mediaFileExtensionListProvider->method('getAllowedExtensions')->willReturn(['jpg', 'png']);

        $validator = new MediaFileExtensionValidator($mediaFileExtensionListProvider);

        $this->expectExceptionObject(MediaException::fileExtensionNotSupported('media-42', 'php'));

        $validator->validate('php', false, Context::createDefaultContext(), 'media-42');
    }

    #[DataProvider('matchingContentTypeProvider')]
    public function testValidateContentTypeAcceptsMatchingOrPassiveContent(string $extension, string $mimeType): void
    {
        $validator = new MediaFileExtensionValidator(static::createStub(MediaFileExtensionListProvider::class));

        $this->expectNotToPerformAssertions();
        $validator->validateContentType($extension, $mimeType);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function matchingContentTypeProvider(): iterable
    {
        yield 'image as jpg' => ['jpg', 'image/jpeg'];
        yield 'plain text as csv' => ['csv', 'text/plain'];
        yield 'excel csv type from windows browsers' => ['csv', 'application/vnd.ms-excel'];
        yield 'unknown binary as glb' => ['glb', 'application/octet-stream'];
        yield 'svg as svg' => ['svg', 'image/svg+xml'];
        yield 'svg with upper case extension' => ['SVG', 'image/svg+xml'];
        yield 'svg detected as text xml' => ['svg', 'text/xml'];
        yield 'svg detected as application xml with charset' => ['SVG', 'application/xml; charset=utf-8'];
        yield 'html as html' => ['html', 'text/html'];
        yield 'html with charset as html' => ['html', 'text/html; charset=utf-8'];
        yield 'xml as xml' => ['xml', 'application/xml'];
    }

    #[DataProvider('mismatchingContentTypeProvider')]
    public function testValidateContentTypeRejectsActiveContentWithForeignExtension(string $extension, string $mimeType, string $reportedContentType): void
    {
        $validator = new MediaFileExtensionValidator(static::createStub(MediaFileExtensionListProvider::class));

        $this->expectExceptionObject(MediaException::invalidFile(
            \sprintf('The content type "%s" does not match the file extension "%s"', $reportedContentType, $extension)
        ));

        $validator->validateContentType($extension, $mimeType);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function mismatchingContentTypeProvider(): iterable
    {
        yield 'html as jpg' => ['jpg', 'text/html', 'text/html'];
        yield 'html with charset as txt' => ['txt', 'text/html; charset=utf-8', 'text/html'];
        yield 'upper case html as png' => ['png', 'TEXT/HTML', 'text/html'];
        yield 'xhtml as pdf' => ['pdf', 'application/xhtml+xml', 'application/xhtml+xml'];
        yield 'svg as png' => ['png', 'image/svg+xml', 'image/svg+xml'];
        yield 'xml as csv' => ['csv', 'text/xml', 'text/xml'];
        yield 'other xml based type as jpg' => ['jpg', 'application/rss+xml', 'application/rss+xml'];
        yield 'html as svg' => ['svg', 'text/html', 'text/html'];
    }
}
