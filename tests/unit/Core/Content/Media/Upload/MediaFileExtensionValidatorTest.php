<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Upload;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
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
    private MediaFileExtensionListProvider&MockObject $mediaFileExtensionListProvider;

    protected function setUp(): void
    {
        $this->mediaFileExtensionListProvider = $this->createMock(MediaFileExtensionListProvider::class);
    }

    public function testValidateAllowsPublicExtension(): void
    {
        $context = Context::createDefaultContext();

        $this->mediaFileExtensionListProvider->expects($this->once())
            ->method('getAllowedExtensions')
            ->with(false, $context)
            ->willReturn(['jpg', 'png']);

        $validator = new MediaFileExtensionValidator($this->mediaFileExtensionListProvider);
        $validator->validate('jpg', false, $context);
    }

    public function testValidateAllowsPrivateExtension(): void
    {
        $context = Context::createDefaultContext();

        $this->mediaFileExtensionListProvider->expects($this->once())
            ->method('getAllowedExtensions')
            ->with(true, $context)
            ->willReturn(['pdf']);

        $validator = new MediaFileExtensionValidator($this->mediaFileExtensionListProvider);
        $validator->validate('pdf', true, $context);
    }

    public function testValidateIsCaseInsensitive(): void
    {
        $this->mediaFileExtensionListProvider->method('getAllowedExtensions')->willReturn(['JPG']);

        $validator = new MediaFileExtensionValidator($this->mediaFileExtensionListProvider);

        $this->expectNotToPerformAssertions();
        $validator->validate('jpg', false, Context::createDefaultContext());
    }

    public function testValidateThrowsWhenExtensionIsNotAllowed(): void
    {
        $this->mediaFileExtensionListProvider->method('getAllowedExtensions')->willReturn(['jpg', 'png']);

        $validator = new MediaFileExtensionValidator($this->mediaFileExtensionListProvider);

        $this->expectExceptionObject(MediaException::fileExtensionNotSupported('media-42', 'php'));

        $validator->validate('php', false, Context::createDefaultContext(), 'media-42');
    }
}
