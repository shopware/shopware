<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CacheValueCompressor::class)]
class CacheValueCompressorTest extends TestCase
{
    private bool $compress;

    private string $compressMethod;

    protected function setUp(): void
    {
        $this->compress = CacheValueCompressor::$compress;
        $this->compressMethod = CacheValueCompressor::$compressMethod;
    }

    protected function tearDown(): void
    {
        CacheValueCompressor::$compress = $this->compress;
        CacheValueCompressor::$compressMethod = $this->compressMethod;
    }

    /**
     * @return iterable<string, array{bool, string, bool, string}>
     */
    public static function configurationProvider(): iterable
    {
        yield 'gzip written, gzip configured' => [true, 'gzip', true, 'gzip'];
        yield 'gzip written, compression disabled' => [true, 'gzip', false, 'gzip'];
        yield 'uncompressed written, gzip configured' => [false, 'gzip', true, 'gzip'];
        yield 'uncompressed written, compression disabled' => [false, 'gzip', false, 'gzip'];
        yield 'gzip written, unknown method configured' => [true, 'gzip', true, 'unknown'];
    }

    #[DataProvider('configurationProvider')]
    public function testUncompressReadsValuesWrittenWithAnyConfiguration(
        bool $writeCompressed,
        string $writeMethod,
        bool $readCompressed,
        string $readMethod,
    ): void {
        $content = ['config' => ['core.basicInformation.shopName' => 'Demostore']];

        CacheValueCompressor::$compress = $writeCompressed;
        CacheValueCompressor::$compressMethod = $writeMethod;
        $stored = CacheValueCompressor::compress($content);

        CacheValueCompressor::$compress = $readCompressed;
        CacheValueCompressor::$compressMethod = $readMethod;

        static::assertSame($content, CacheValueCompressor::uncompress($stored));
    }

    #[RequiresPhpExtension('zstd')]
    public function testUncompressReadsGzipValuesWhileZstdIsConfigured(): void
    {
        $content = ['config' => ['core.basicInformation.shopName' => 'Demostore']];

        CacheValueCompressor::$compress = true;
        CacheValueCompressor::$compressMethod = 'gzip';
        $stored = CacheValueCompressor::compress($content);

        CacheValueCompressor::$compressMethod = 'zstd';

        static::assertSame($content, CacheValueCompressor::uncompress($stored));
    }

    #[RequiresPhpExtension('zstd')]
    public function testUncompressReadsZstdValuesWhileGzipIsConfigured(): void
    {
        $content = ['config' => ['core.basicInformation.shopName' => 'Demostore']];

        CacheValueCompressor::$compress = true;
        CacheValueCompressor::$compressMethod = 'zstd';
        $stored = CacheValueCompressor::compress($content);

        CacheValueCompressor::$compressMethod = 'gzip';

        static::assertSame($content, CacheValueCompressor::uncompress($stored));
    }

    public function testUncompressReturnsNonStringValuesUnchanged(): void
    {
        $content = ['already' => 'unserialized'];

        static::assertSame($content, CacheValueCompressor::uncompress($content));
    }
}
