<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\Struct\ArrayStruct;

/**
 * @internal
 */
#[CoversClass(CacheValueCompressor::class)]
class CacheValueCompressorTest extends TestCase
{
    private bool $compress;

    private string $compressMethod;

    private string $serializeMethod;

    protected function setUp(): void
    {
        // CacheValueCompressor is configured via static properties; snapshot and restore them.
        $this->compress = CacheValueCompressor::$compress;
        $this->compressMethod = CacheValueCompressor::$compressMethod;
        $this->serializeMethod = CacheValueCompressor::$serializeMethod;
    }

    protected function tearDown(): void
    {
        CacheValueCompressor::$compress = $this->compress;
        CacheValueCompressor::$compressMethod = $this->compressMethod;
        CacheValueCompressor::$serializeMethod = $this->serializeMethod;
    }

    /**
     * @param array{compress: bool, method: string, serialize: string} $config
     */
    #[DataProvider('roundTripProvider')]
    public function testRoundTrip(array $config): void
    {
        if ($config['serialize'] === 'igbinary') {
            $this->skipWithoutIgbinary();
        }

        CacheValueCompressor::$compress = $config['compress'];
        CacheValueCompressor::$compressMethod = $config['method'];
        CacheValueCompressor::$serializeMethod = $config['serialize'];

        $value = self::payload();

        $restored = CacheValueCompressor::uncompress(CacheValueCompressor::compress($value));

        static::assertEquals($value, $restored);
    }

    public static function roundTripProvider(): \Generator
    {
        yield 'serialize + gzip' => [['compress' => true, 'method' => 'gzip', 'serialize' => 'serialize']];
        yield 'serialize uncompressed' => [['compress' => false, 'method' => 'gzip', 'serialize' => 'serialize']];
        yield 'igbinary + gzip' => [['compress' => true, 'method' => 'gzip', 'serialize' => 'igbinary']];
        yield 'igbinary uncompressed' => [['compress' => false, 'method' => 'gzip', 'serialize' => 'igbinary']];
    }

    public function testNonStringValueIsReturnedAsIs(): void
    {
        $value = self::payload();

        static::assertSame($value, CacheValueCompressor::uncompress($value));
    }

    public function testIgbinaryWrittenValueIsReadableAfterSwitchingBackToSerialize(): void
    {
        $this->skipWithoutIgbinary();

        $value = self::payload();

        // written by a worker/deploy that has igbinary enabled
        CacheValueCompressor::$compress = true;
        CacheValueCompressor::$compressMethod = 'gzip';
        CacheValueCompressor::$serializeMethod = 'igbinary';
        $compressed = CacheValueCompressor::compress($value);

        // read by a worker that is configured back to native serialize -> auto-detection must win
        CacheValueCompressor::$serializeMethod = 'serialize';

        static::assertEquals($value, CacheValueCompressor::uncompress($compressed));
    }

    public function testSerializeWrittenValueIsReadableWhenIgbinaryIsEnabled(): void
    {
        $this->skipWithoutIgbinary();

        $value = self::payload();

        // legacy value already in the cache, written with native serialize
        CacheValueCompressor::$compress = true;
        CacheValueCompressor::$compressMethod = 'gzip';
        CacheValueCompressor::$serializeMethod = 'serialize';
        $compressed = CacheValueCompressor::compress($value);

        // operator opts into igbinary -> old values must still decode
        CacheValueCompressor::$serializeMethod = 'igbinary';

        static::assertEquals($value, CacheValueCompressor::uncompress($compressed));
    }

    private function skipWithoutIgbinary(): void
    {
        if (!\function_exists('igbinary_serialize')) {
            static::markTestSkipped('ext-igbinary is not installed');
        }
    }

    private static function payload(): ArrayStruct
    {
        return new ArrayStruct([
            'id' => 'abc',
            'nested' => ['a' => 1, 'b' => [2, 3, 4], 'c' => null],
            'flag' => true,
            'float' => 12.34,
        ]);
    }
}
