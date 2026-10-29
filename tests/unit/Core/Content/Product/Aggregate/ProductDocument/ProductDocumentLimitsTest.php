<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Aggregate\ProductDocument;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentLimits;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\MemorySizeCalculator;
use Shopware\Tests\Unit\Common\Stubs\IniMock;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductDocumentLimits::class)]
class ProductDocumentLimitsTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        IniMock::register(MemorySizeCalculator::class);
    }

    protected function setUp(): void
    {
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
        $limits = new ProductDocumentLimits(['PDF', ' .docx', 'pdf', ''], maxPerLanguage: 10, maxFileSize: 1024);

        static::assertSame(['pdf', 'docx'], $limits->allowedExtensions);
    }

    public function testEachFileTypeHasItsMimeTypes(): void
    {
        $limits = new ProductDocumentLimits(['pdf', 'docx'], maxPerLanguage: 10, maxFileSize: 1024);

        $mimeTypes = $limits->getMimeTypesByExtension();

        static::assertSame(['pdf', 'docx'], array_keys($mimeTypes));
        static::assertContains('application/pdf', $mimeTypes['pdf']);
        static::assertContains('application/vnd.openxmlformats-officedocument.wordprocessingml.document', $mimeTypes['docx']);
    }

    public function testAConfiguredSizeBelowThePhpLimitsIsTheUploadLimit(): void
    {
        $limits = new ProductDocumentLimits(['pdf'], maxPerLanguage: 10, maxFileSize: 1024);

        static::assertSame(1024, $limits->getMaxUploadFileSize());
    }

    public function testThePhpLimitsLowerALargerConfiguredSizeForUploadsOnly(): void
    {
        $limits = new ProductDocumentLimits(['pdf'], maxPerLanguage: 10, maxFileSize: 20 * 1024 * 1024);

        static::assertSame(8 * 1024 * 1024, $limits->getMaxUploadFileSize());
        static::assertSame(20 * 1024 * 1024, $limits->maxFileSize);
    }
}
