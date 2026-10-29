<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Aggregate\ProductDocument;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentLimits;
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
#[CoversClass(ProductDocumentLimits::class)]
class ProductDocumentLimitsTest extends TestCase
{
    private EventDispatcher $eventDispatcher;

    public static function setUpBeforeClass(): void
    {
        IniMock::register(MemorySizeCalculator::class);
    }

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();

        IniMock::withIniMock([
            'upload_max_filesize' => '8M',
            'post_max_size' => '16M',
        ]);
    }

    protected function tearDown(): void
    {
        IniMock::withIniMock([]);
    }

    public function testFileTypesAreCleanedUp(): void
    {
        $limits = new ProductDocumentLimits($this->eventDispatcher, ['PDF', ' .docx', 'pdf', ''], maxPerLanguage: 10, maxFileSize: 1024);

        static::assertSame(['pdf', 'docx'], $limits->getAllowedExtensions(Context::createDefaultContext()));
    }

    public function testExtensionsCanChangeTheFileTypes(): void
    {
        $context = Context::createDefaultContext();
        $receivedContext = null;
        $this->eventDispatcher->addListener(ProductDocumentAllowedExtensionsEvent::class, static function (ProductDocumentAllowedExtensionsEvent $event) use (&$receivedContext): void {
            $receivedContext = $event->getContext();
            $event->setAllowedExtensions([...array_diff($event->getAllowedExtensions(), ['docx']), 'XLSX']);
        });

        $limits = new ProductDocumentLimits($this->eventDispatcher, ['pdf', 'docx'], maxPerLanguage: 10, maxFileSize: 1024);

        static::assertSame(['pdf', 'xlsx'], $limits->getAllowedExtensions($context));
        static::assertSame($context, $receivedContext);
    }

    public function testEachFileTypeHasItsMimeTypes(): void
    {
        $limits = new ProductDocumentLimits($this->eventDispatcher, ['pdf', 'docx'], maxPerLanguage: 10, maxFileSize: 1024);

        $mimeTypes = $limits->getMimeTypesByExtension(Context::createDefaultContext());

        static::assertSame(['pdf', 'docx'], array_keys($mimeTypes));
        static::assertContains('application/pdf', $mimeTypes['pdf']);
        static::assertContains('application/vnd.openxmlformats-officedocument.wordprocessingml.document', $mimeTypes['docx']);
    }

    public function testAConfiguredSizeBelowThePhpLimitsIsTheUploadLimit(): void
    {
        $limits = new ProductDocumentLimits($this->eventDispatcher, ['pdf'], maxPerLanguage: 10, maxFileSize: 1024);

        static::assertSame(1024, $limits->getMaxUploadFileSize());
    }

    public function testThePhpLimitsLowerALargerConfiguredSizeForUploadsOnly(): void
    {
        $limits = new ProductDocumentLimits($this->eventDispatcher, ['pdf'], maxPerLanguage: 10, maxFileSize: 20 * 1024 * 1024);

        static::assertSame(8 * 1024 * 1024, $limits->getMaxUploadFileSize());
        static::assertSame(20 * 1024 * 1024, $limits->maxFileSize);
    }
}
