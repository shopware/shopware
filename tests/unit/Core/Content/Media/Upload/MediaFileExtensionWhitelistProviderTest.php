<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Upload;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Event\MediaFileExtensionWhitelistEvent;
use Shopware\Core\Content\Media\Upload\MediaFileExtensionWhitelistProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MediaFileExtensionWhitelistProvider::class)]
class MediaFileExtensionWhitelistProviderTest extends TestCase
{
    public function testReturnsPublicAndPrivateExtensionsSeparately(): void
    {
        $provider = new MediaFileExtensionWhitelistProvider(new EventDispatcher(), ['jpg', 'png'], ['pdf']);
        $context = Context::createDefaultContext();

        static::assertSame(['jpg', 'png'], $provider->getAllowedExtensions(false, $context));
        static::assertSame(['pdf'], $provider->getAllowedExtensions(true, $context));
    }

    public function testReturnsExtensionsAddedByWhitelistEvent(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(MediaFileExtensionWhitelistEvent::class, static function (MediaFileExtensionWhitelistEvent $event): void {
            $whitelist = $event->getWhitelist();
            $whitelist[] = 'epub';

            $event->setWhitelist($whitelist);
        });

        $provider = new MediaFileExtensionWhitelistProvider($eventDispatcher, ['jpg'], ['pdf']);

        static::assertSame(['pdf', 'epub'], $provider->getAllowedExtensions(true, Context::createDefaultContext()));
    }

    public function testNormalizesAndDeduplicatesExtensions(): void
    {
        $provider = new MediaFileExtensionWhitelistProvider(new EventDispatcher(), [' JPG ', '.jpg', 'Pdf', '', '.EPUB'], []);

        static::assertSame(['jpg', 'pdf', 'epub'], $provider->getAllowedExtensions(false, Context::createDefaultContext()));
    }

    public function testResolvesMimeTypesByExtension(): void
    {
        $provider = new MediaFileExtensionWhitelistProvider(new EventDispatcher(), [], ['epub']);

        static::assertSame(
            ['epub' => ['application/epub+zip']],
            $provider->getMimeTypesByExtension(true, Context::createDefaultContext())
        );
    }

    public function testUnknownExtensionsRemainAllowedWithoutMimeTypes(): void
    {
        $provider = new MediaFileExtensionWhitelistProvider(new EventDispatcher(), [], ['unknown-shopware-extension']);
        $context = Context::createDefaultContext();

        static::assertSame(['unknown-shopware-extension'], $provider->getAllowedExtensions(true, $context));
        static::assertSame(
            ['unknown-shopware-extension' => []],
            $provider->getMimeTypesByExtension(true, $context)
        );
    }
}
