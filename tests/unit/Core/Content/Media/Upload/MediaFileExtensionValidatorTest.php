<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Upload;

use PHPUnit\Framework\Attributes\CoversClass;
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
}
