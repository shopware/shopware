<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\OnDelete;
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

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function serializedValueProvider(): iterable
    {
        yield 'null' => [null];
        yield 'false' => [false];
        yield 'integer' => [42];
        yield 'float' => [4.2];
        yield 'string' => ['Demostore'];
        yield 'array' => [['config' => ['core.basicInformation.shopName' => 'Demostore']]];
        yield 'object' => [new \ArrayObject(['shopName' => 'Demostore'])];
        yield 'enum' => [OnDelete::CASCADE];
    }

    #[DataProvider('serializedValueProvider')]
    public function testUncompressReadsUncompressedValuesOfAnyType(mixed $content): void
    {
        CacheValueCompressor::$compress = false;
        $stored = CacheValueCompressor::compress($content);

        CacheValueCompressor::$compress = true;

        static::assertEquals($content, CacheValueCompressor::uncompress($stored));
    }

    public function testUncompressReadsObjectsSerializedThroughTheSerializableInterface(): void
    {
        $content = new \ArrayObject(['shopName' => 'Demostore']);
        $data = $content->serialize();
        $stored = 'C:11:"ArrayObject":' . \strlen($data) . ':{' . $data . '}';

        static::assertEquals($content, CacheValueCompressor::uncompress($stored));
    }

    public function testUncompressThrowsForValuesInAnUnknownFormat(): void
    {
        $this->expectExceptionObject(AdapterException::cacheCompressionError('Unknown cache value format'));

        CacheValueCompressor::uncompress('not a cache value');
    }

    public function testUncompressThrowsForZstdValuesWithoutTheZstdExtension(): void
    {
        if (\function_exists('zstd_uncompress')) {
            static::markTestSkipped('The zstd PHP extension is loaded.');
        }

        $this->expectExceptionObject(AdapterException::cacheCompressionError('Value is zstd compressed, but the zstd PHP extension is not loaded'));

        CacheValueCompressor::uncompress("\x28\xB5\x2F\xFD" . 'zstd frame');
    }

    public function testUncompressReturnsNonStringValuesUnchanged(): void
    {
        $content = ['already' => 'unserialized'];

        static::assertSame($content, CacheValueCompressor::uncompress($content));
    }
}
