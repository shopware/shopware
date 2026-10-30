<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Aggregate\ProductDocument;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentLimits;
use Shopware\Core\Framework\Log\Package;
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
        IniMock::register(ProductDocumentLimits::class);
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

    /**
     * @return iterable<string, array{int, bool, string, int}>
     */
    public static function uploadLimitProvider(): iterable
    {
        yield 'a configured size below post_max_size' => [1024, false, '16M', 1024];
        yield 'post_max_size lowers a larger configured size' => [20 * 1024 * 1024, false, '16M', 16 * 1024 * 1024];
        yield 'no post_max_size' => [20 * 1024 * 1024, false, '0', 20 * 1024 * 1024];
        yield 'a presigned upload goes past PHP' => [20 * 1024 * 1024, true, '16M', 20 * 1024 * 1024];
    }

    #[DataProvider('uploadLimitProvider')]
    public function testTheUploadLimitIsTheConfiguredSizeLoweredByPostMaxSize(int $maxFileSize, bool $presignedUploadSupported, string $postMaxSize, int $expected): void
    {
        // the Administration uploads the raw file, so upload_max_filesize doesn't apply
        IniMock::withIniMock(['upload_max_filesize' => '8M', 'post_max_size' => $postMaxSize]);

        $limits = new ProductDocumentLimits(['pdf'], maxPerLanguage: 10, maxFileSize: $maxFileSize);

        static::assertSame($expected, $limits->getMaxUploadFileSize($presignedUploadSupported));
        static::assertSame($maxFileSize, $limits->maxFileSize);
    }
}
