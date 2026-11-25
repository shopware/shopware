<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Aggregate\ProductDocument;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Event\MediaFileExtensionWhitelistEvent;
use Shopware\Core\Content\Media\Upload\MediaFileExtensionListProvider;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentConfig;
use Shopware\Core\Content\Product\Events\ProductDocumentAllowedExtensionsEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductDocumentConfig::class)]
class ProductDocumentConfigTest extends TestCase
{
    public function testFileTypesAreCleanedUp(): void
    {
        $context = Context::createDefaultContext();
        $config = self::config(['DOCX', ' .pdf', 'pdf', '']);

        static::assertSame(['pdf', 'docx'], $config->getAllowedExtensions($context));
    }

    public function testNumericFileTypesStayStrings(): void
    {
        $context = Context::createDefaultContext();
        $config = self::config(['pdf', 264, '7z']);

        static::assertSame(['pdf', '264', '7z'], $config->getAllowedExtensions($context));
    }

    public function testExtensionsCanChangeTheFileTypes(): void
    {
        $context = Context::createDefaultContext();
        $events = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ProductDocumentAllowedExtensionsEvent::class, static function (ProductDocumentAllowedExtensionsEvent $event) use (&$events): void {
            $events[] = $event;
            $event->setAllowedExtensions(['pdf', '.XLSX']);
        });

        $config = self::config(['pdf', 'docx'], dispatcher: $dispatcher);

        static::assertSame(['pdf', 'xlsx'], $config->getAllowedExtensions($context));
        static::assertCount(1, $events);
        static::assertSame($context, $events[0]->getContext());
    }

    public function testOnlyFileTypesThatPublicMediaAcceptsAreAllowed(): void
    {
        $context = Context::createDefaultContext();
        $config = self::config(['pdf', 'zip', 'docx'], publicMediaExtensions: ['pdf', 'docx']);

        static::assertSame(['pdf', 'docx'], $config->getAllowedExtensions($context));
    }

    public function testFileTypesThatExtensionsAllowForPublicMediaAreAllowed(): void
    {
        $context = Context::createDefaultContext();
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(MediaFileExtensionWhitelistEvent::class, static function (MediaFileExtensionWhitelistEvent $event): void {
            $event->setWhitelist([...$event->getWhitelist(), 'zip']);
        });

        $config = self::config(['pdf', 'zip'], dispatcher: $dispatcher, publicMediaExtensions: ['pdf']);

        static::assertSame(['pdf', 'zip'], $config->getAllowedExtensions($context));
    }

    /**
     * @param list<string|int> $allowedExtensions
     * @param list<string> $publicMediaExtensions
     */
    private static function config(array $allowedExtensions, int $maxFileSize = 1024, ?EventDispatcher $dispatcher = null, array $publicMediaExtensions = ['pdf', 'docx', 'xlsx', '264', '7z']): ProductDocumentConfig
    {
        $dispatcher ??= new EventDispatcher();

        /** @phpstan-ignore argument.type */
        return new ProductDocumentConfig($dispatcher, $allowedExtensions, $maxFileSize, new MediaFileExtensionListProvider($dispatcher, $publicMediaExtensions, []));
    }
}
