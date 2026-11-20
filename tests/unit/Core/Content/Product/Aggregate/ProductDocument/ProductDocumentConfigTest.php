<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Aggregate\ProductDocument;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentConfig;
use Shopware\Core\Content\Product\Events\ProductDocumentAllowedExtensionsEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\MemorySizeCalculator;
use Shopware\Tests\Unit\Common\Stubs\IniMock;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductDocumentConfig::class)]
class ProductDocumentConfigTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // register before the first ini_get(), or the ini mocks of later tests stop working
        IniMock::register(MemorySizeCalculator::class);
    }

    protected function tearDown(): void
    {
        IniMock::withIniMock([]);
    }

    public function testFileTypesAreCleanedUp(): void
    {
        $context = Context::createDefaultContext();
        $config = self::config(['PDF', ' .docx', 'pdf', '']);

        static::assertSame(['pdf', 'docx'], $config->getAllowedExtensions($context));
        static::assertSame(['pdf', 'docx'], array_keys($config->getMimeTypesByExtension($context)));
    }

    public function testNumericFileTypesStayStrings(): void
    {
        $context = Context::createDefaultContext();
        $config = self::config(['pdf', 264, '7z']);

        static::assertSame(['pdf', '264', '7z'], $config->getAllowedExtensions($context));
        static::assertCount(3, $config->getMimeTypesByExtension($context));
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
        static::assertSame(['pdf', 'docx'], array_keys($config->getMimeTypesByExtension($context)));
        static::assertContains('application/pdf', $config->getMimeTypesByExtension($context)['pdf']);
    }

    public static function uploadLimitProvider(): iterable
    {
        yield 'PHP limits below the configured size lower it' => [20 * 1024 * 1024, 8 * 1024 * 1024];
        yield 'a configured size below the PHP limits stays' => [1024, 1024];
    }

    #[DataProvider('uploadLimitProvider')]
    public function testTheUploadLimitIsTheConfiguredSizeLoweredByPhpLimits(int $maxFileSize, int $expected): void
    {
        IniMock::withIniMock(['upload_max_filesize' => '8M', 'post_max_size' => '16M']);

        $config = self::config(['pdf'], maxFileSize: $maxFileSize);

        static::assertSame($expected, $config->getMaxUploadFileSize());
    }

    /**
     * @param list<string|int> $allowedExtensions
     * @param list<string> $publicMediaExtensions
     */
    private static function config(array $allowedExtensions, int $maxFileSize = 1024, ?EventDispatcher $dispatcher = null, array $publicMediaExtensions = ['pdf', 'docx', 'xlsx', '264', '7z']): ProductDocumentConfig
    {
        $dispatcher ??= new EventDispatcher();

        /** @phpstan-ignore argument.type */
        return new ProductDocumentConfig($dispatcher, $allowedExtensions, $maxFileSize, $publicMediaExtensions);
    }
}
