<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Upload;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Event\MediaFileExtensionWhitelistEvent;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\Upload\MediaFileExtensionValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MediaFileExtensionValidator::class)]
class MediaFileExtensionValidatorTest extends TestCase
{
    private EventDispatcherInterface&MockObject $eventDispatcher;

    protected function setUp(): void
    {
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
    }

    public function testValidateAllowsExtensionOnPublicWhitelist(): void
    {
        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::callback(static function (MediaFileExtensionWhitelistEvent $event): bool {
                static::assertSame(['jpg', 'png'], $event->getWhitelist());

                return true;
            }))
            ->willReturnArgument(0);

        $validator = new MediaFileExtensionValidator($this->eventDispatcher, ['jpg', 'png'], ['pdf']);
        $validator->validate('jpg', false, Context::createDefaultContext());
    }

    public function testValidateAllowsExtensionOnPrivateWhitelist(): void
    {
        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::callback(static function (MediaFileExtensionWhitelistEvent $event): bool {
                static::assertSame(['pdf'], $event->getWhitelist());

                return true;
            }))
            ->willReturnArgument(0);

        $validator = new MediaFileExtensionValidator($this->eventDispatcher, ['jpg'], ['pdf']);
        $validator->validate('pdf', true, Context::createDefaultContext());
    }

    public function testValidateIsCaseInsensitive(): void
    {
        $this->eventDispatcher->method('dispatch')->willReturnArgument(0);

        $validator = new MediaFileExtensionValidator($this->eventDispatcher, ['JPG'], []);

        $this->expectNotToPerformAssertions();
        $validator->validate('jpg', false, Context::createDefaultContext());
    }

    public function testValidateThrowsWhenExtensionNotInWhitelist(): void
    {
        $this->eventDispatcher->method('dispatch')->willReturnArgument(0);

        $validator = new MediaFileExtensionValidator($this->eventDispatcher, ['jpg', 'png'], []);

        $this->expectExceptionObject(MediaException::fileExtensionNotSupported('media-42', 'php'));

        $validator->validate('php', false, Context::createDefaultContext(), 'media-42');
    }

    public function testValidateRespectsListenerModificationOfWhitelist(): void
    {
        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function (MediaFileExtensionWhitelistEvent $event): MediaFileExtensionWhitelistEvent {
                $event->setWhitelist(['txt']);

                return $event;
            });

        $validator = new MediaFileExtensionValidator($this->eventDispatcher, ['jpg'], []);

        // A listener added 'txt' to the whitelist — 'txt' should now be accepted even though it is
        // not in the configured allowedExtensions.
        $validator->validate('txt', false, Context::createDefaultContext());
    }

    public function testValidatePassesContextToWhitelistEvent(): void
    {
        $context = Context::createDefaultContext();

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::callback(static function (MediaFileExtensionWhitelistEvent $event) use ($context): bool {
                static::assertSame($context, $event->getContext());

                return true;
            }))
            ->willReturnArgument(0);

        $validator = new MediaFileExtensionValidator($this->eventDispatcher, ['jpg'], []);
        $validator->validate('jpg', false, $context);
    }
}
