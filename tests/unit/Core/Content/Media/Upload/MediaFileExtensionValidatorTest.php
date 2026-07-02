<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Upload;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Event\MediaFileExtensionWhitelistEvent;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\Upload\MediaFileExtensionValidator;
use Shopware\Core\Content\Media\Upload\MediaFileExtensionWhitelistProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MediaFileExtensionValidator::class)]
class MediaFileExtensionValidatorTest extends TestCase
{
    private MediaFileExtensionWhitelistProvider&MockObject $whitelistProvider;

    protected function setUp(): void
    {
        $this->whitelistProvider = $this->createMock(MediaFileExtensionWhitelistProvider::class);
    }

    public function testValidateAllowsExtensionOnPublicWhitelist(): void
    {
        $context = Context::createDefaultContext();

        $this->whitelistProvider->expects($this->once())
            ->method('getAllowedExtensions')
            ->with(false, $context)
            ->willReturn(['jpg', 'png']);

        $validator = new MediaFileExtensionValidator($this->whitelistProvider);
        $validator->validate('jpg', false, $context);
    }

    public function testValidateAllowsExtensionOnPrivateWhitelist(): void
    {
        $context = Context::createDefaultContext();

        $this->whitelistProvider->expects($this->once())
            ->method('getAllowedExtensions')
            ->with(true, $context)
            ->willReturn(['pdf']);

        $validator = new MediaFileExtensionValidator($this->whitelistProvider);
        $validator->validate('pdf', true, $context);
    }

    public function testValidateIsCaseInsensitive(): void
    {
        $this->whitelistProvider->method('getAllowedExtensions')->willReturn(['JPG']);

        $validator = new MediaFileExtensionValidator($this->whitelistProvider);

        $this->expectNotToPerformAssertions();
        $validator->validate('jpg', false, Context::createDefaultContext());
    }

    public function testValidateThrowsWhenExtensionNotInWhitelist(): void
    {
        $this->whitelistProvider->method('getAllowedExtensions')->willReturn(['jpg', 'png']);

        $validator = new MediaFileExtensionValidator($this->whitelistProvider);

        $this->expectExceptionObject(MediaException::fileExtensionNotSupported('media-42', 'php'));

        $validator->validate('php', false, Context::createDefaultContext(), 'media-42');
    }

    public function testValidateSupportsLegacyConstructorArguments(): void
    {
        $context = Context::createDefaultContext();
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            MediaFileExtensionWhitelistEvent::class,
            static function (MediaFileExtensionWhitelistEvent $event): void {
                $event->setWhitelist([...$event->getWhitelist(), 'epub']);
            }
        );

        $validator = new MediaFileExtensionValidator($eventDispatcher, ['jpg'], ['pdf']);

        $this->expectNotToPerformAssertions();
        $validator->validate('epub', false, $context);
    }
}
