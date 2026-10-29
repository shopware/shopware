<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Aggregate\ProductDocument;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentLimits;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\MemorySizeCalculator;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductDocumentLimits::class)]
class ProductDocumentLimitsTest extends TestCase
{
    public function testFileTypesAreComparedWithoutCaseAndLeadingDot(): void
    {
        $limits = new ProductDocumentLimits(['PDF', ' .docx', 'pdf'], maxPerLanguage: 10, maxFileSize: 1024);

        static::assertSame(['pdf', 'docx'], $limits->allowedExtensions);
        static::assertTrue($limits->isAllowedExtension('pdf'));
        static::assertTrue($limits->isAllowedExtension('DOCX'));
        static::assertFalse($limits->isAllowedExtension('exe'));
        static::assertFalse($limits->isAllowedExtension(null));
    }

    public function testAConfiguredSizeBelowThePhpLimitsIsTheUploadLimit(): void
    {
        $limits = new ProductDocumentLimits(['pdf'], maxPerLanguage: 10, maxFileSize: 1024);

        static::assertSame(1024, $limits->getMaxUploadFileSize());
    }

    public function testThePhpLimitsLowerALargerConfiguredSizeForUploadsOnly(): void
    {
        $limits = new ProductDocumentLimits(['pdf'], maxPerLanguage: 10, maxFileSize: \PHP_INT_MAX);

        static::assertSame(MemorySizeCalculator::getMaxUploadSize(), $limits->getMaxUploadFileSize());
        static::assertSame(\PHP_INT_MAX, $limits->maxFileSize);
    }
}
