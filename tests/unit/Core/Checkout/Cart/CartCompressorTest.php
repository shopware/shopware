<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartCompressor;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(CartCompressor::class)]
class CartCompressorTest extends TestCase
{
    public function testSerializeNone(): void
    {
        $compressor = new CartCompressor(false, 'gzip');
        [$compression, $result] = $compressor->serialize('test');

        static::assertSame(0, $compression);
        static::assertIsString($result);

        $back = $compressor->unserialize($result, $compression);

        static::assertSame('test', $back);
        static::assertSame('test', unserialize($result));
    }

    public function testSerializeGzip(): void
    {
        $compressor = new CartCompressor(true, 'gzip');
        [$compression, $result] = $compressor->serialize('test');

        static::assertSame(1, $compression);
        static::assertIsString($result);

        $back = $compressor->unserialize($result, $compression);

        static::assertSame('test', $back);
    }

    public function testSerializeZstd(): void
    {
        if (!\function_exists('zstd_compress')) {
            static::markTestSkipped('zstd extension is not installed');
        }

        $compressor = new CartCompressor(true, 'zstd');
        [$compression, $result] = $compressor->serialize('test');

        static::assertSame(2, $compression);
        static::assertIsString($result);

        $back = $compressor->unserialize($result, $compression);

        static::assertSame('test', $back);
    }

    public function testInvalidCompression(): void
    {
        static::expectExceptionObject(CartException::invalidCompressionMethod('invalid'));
        new CartCompressor(true, 'invalid');
    }

    public function testInvalidUnserialize(): void
    {
        $compressor = new CartCompressor(true, 'gzip');

        static::expectExceptionObject(CartException::deserializeFailed());
        $compressor->unserialize('invalid', 1);
    }
}
