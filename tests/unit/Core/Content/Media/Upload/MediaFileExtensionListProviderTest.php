<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Upload;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Event\MediaFileExtensionWhitelistEvent;
use Shopware\Core\Content\Media\Upload\MediaFileExtensionListProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MediaFileExtensionListProvider::class)]
class MediaFileExtensionListProviderTest extends TestCase
{
    public function testReturnsPublicAndPrivateExtensionsSeparately(): void
    {
        $provider = new MediaFileExtensionListProvider(new EventDispatcher(), ['jpg', 'png'], ['pdf']);
        $context = Context::createDefaultContext();

        static::assertSame(['jpg', 'png'], $provider->getAllowedExtensions(false, $context));
        static::assertSame(['pdf'], $provider->getAllowedExtensions(true, $context));
    }

    public function testReturnsExtensionsAddedByEvent(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(MediaFileExtensionWhitelistEvent::class, static function (MediaFileExtensionWhitelistEvent $event): void {
            $extensions = $event->getWhitelist();
            $extensions[] = 'epub';

            $event->setWhitelist($extensions);
        });

        $provider = new MediaFileExtensionListProvider($eventDispatcher, ['jpg'], ['pdf']);

        static::assertSame(['pdf', 'epub'], $provider->getAllowedExtensions(true, Context::createDefaultContext()));
    }

    public function testPassesContextToWhitelistEvent(): void
    {
        $context = Context::createDefaultContext();

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::callback(static function (MediaFileExtensionWhitelistEvent $event) use ($context): bool {
                static::assertSame($context, $event->getContext());

                return true;
            }))
            ->willReturnArgument(0);

        $provider = new MediaFileExtensionListProvider($eventDispatcher, ['jpg'], []);

        static::assertSame(['jpg'], $provider->getAllowedExtensions(false, $context));
    }

    public function testNormalizesAndDeduplicatesExtensions(): void
    {
        $provider = new MediaFileExtensionListProvider(new EventDispatcher(), [' JPG ', '.jpg', 'Pdf', '', '.EPUB'], []);

        static::assertSame(['jpg', 'pdf', 'epub'], $provider->getAllowedExtensions(false, Context::createDefaultContext()));
    }

    public function testResolvesMimeTypesByExtension(): void
    {
        $provider = new MediaFileExtensionListProvider(new EventDispatcher(), [], ['epub']);

        static::assertSame(
            ['epub' => ['application/epub+zip']],
            $provider->getMimeTypesByExtension(true, Context::createDefaultContext())
        );
    }

    public function testUnknownExtensionsRemainAllowedWithoutMimeTypes(): void
    {
        $provider = new MediaFileExtensionListProvider(new EventDispatcher(), [], ['unknown-shopware-extension']);
        $context = Context::createDefaultContext();

        static::assertSame(['unknown-shopware-extension'], $provider->getAllowedExtensions(true, $context));
        static::assertSame(
            ['unknown-shopware-extension' => []],
            $provider->getMimeTypesByExtension(true, $context)
        );
    }
}
