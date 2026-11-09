<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Aggregate\ProductDocument;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Common\Stubs\IniMock;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductDocumentConfig::class)]
class ProductDocumentConfigTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        IniMock::register(ProductDocumentConfig::class);
    }

    protected function tearDown(): void
    {
        IniMock::withIniMock([]);
    }

    public function testFileTypesAreCleanedUp(): void
    {
        $config = new ProductDocumentConfig(['PDF', ' .docx', 'pdf', ''], maxPerProduct: 10, maxFileSize: 1024);

        static::assertSame(['pdf', 'docx'], $config->allowedExtensions);
        static::assertSame(['pdf', 'docx'], array_keys($config->mimeTypesByExtension));
    }

    /**
     * @return iterable<string, array{int, string, int}>
     */
    public static function uploadLimitProvider(): iterable
    {
        yield 'a configured size below post_max_size' => [1024, '16M', 1024];
        yield 'post_max_size lowers a larger configured size' => [20 * 1024 * 1024, '16M', 16 * 1024 * 1024];
        yield 'no post_max_size' => [20 * 1024 * 1024, '0', 20 * 1024 * 1024];
    }

    #[DataProvider('uploadLimitProvider')]
    public function testTheUploadLimitIsTheConfiguredSizeLoweredByPostMaxSize(int $maxFileSize, string $postMaxSize, int $expected): void
    {
        // the Administration uploads the raw file, so upload_max_filesize doesn't apply
        IniMock::withIniMock(['upload_max_filesize' => '8M', 'post_max_size' => $postMaxSize]);

        $config = new ProductDocumentConfig(['pdf'], maxPerProduct: 10, maxFileSize: $maxFileSize);

        static::assertSame($expected, $config->getMaxUploadFileSize());
        static::assertSame($maxFileSize, $config->maxFileSize);
    }
}
