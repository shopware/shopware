<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Util\MemorySizeCalculator;
use Shopware\Tests\Unit\Common\Stubs\IniMock;

/**
 * @internal
 */
#[CoversClass(MemorySizeCalculator::class)]
class MemorySizeCalculatorTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        IniMock::register(MemorySizeCalculator::class);
    }

    #[DataProvider('memorySizeDataProvider')]
    public function testBytesConversion(string $limit, int $bytes): void
    {
        static::assertSame($bytes, MemorySizeCalculator::convertToBytes($limit));
    }

    /**
     * We are trying to replicate the Symfony's convertToBytes method. Therefore, we will use the test cases Symfony
     * uses.
     *
     * See also:
     * https://github.com/symfony/symfony/blob/3a96e4cde6aa0c9e138bdfcce60564a2f396c070/src/Symfony/Component/HttpKernel/Tests/DataCollector/MemoryDataCollectorTest.php
     *
     * @return iterable<string, array{0: string, 1: int}>
     */
    public static function memorySizeDataProvider(): iterable
    {
        yield 'memory size 2k 2048' => ['2k', 2048];
        yield 'memory size 2 k 2048' => ['2 k', 2048];
        yield 'memory size 8m 8' => ['8m', 8 * 1024 * 1024];
        yield 'memory size 2 k 2048 variant 2' => ['+2 k', 2048];
        yield 'memory size 2 k 2048 variant 3' => ['+2???k', 2048];
        yield 'memory size 0x10 16' => ['0x10', 16];
        yield 'memory size 0xf 15' => ['0xf', 15];
        yield 'memory size 010 8' => ['010', 8];
        yield 'memory size 0x10 k 16' => ['+0x10 k', 16 * 1024];
        yield 'memory size 1g 1024' => ['1g', 1024 * 1024 * 1024];
        yield 'memory size 1 g 1024' => ['1G', 1024 * 1024 * 1024];
        yield 'memory size 1 -1' => ['-1', -1];
        yield 'memory size 0 0' => ['0', 0];
        // the unit must be the last char, so in this case 'k', not 'm'
        yield 'memory size uses the last unit character' => ['2mk', 2048];
    }

    #[DataProvider('bytesProvider')]
    public function testFormatBytes(int $bytes, string $formatted): void
    {
        static::assertSame($formatted, MemorySizeCalculator::formatToBytes($bytes));
    }

    /**
     * @return iterable<array{0: int, 1: string}>
     */
    public static function bytesProvider(): iterable
    {
        yield 'bytes 0 0 b' => [0, '0 B'];
        yield 'bytes 100 100 b' => [100, '100 B'];
        yield 'bytes 1024 1 kb' => [1024, '1 KB'];
        yield 'bytes 2024 1 98 kb' => [2024, '1.98 KB'];
        yield 'bytes 20240 19 77 kb' => [20240, '19.77 KB'];
        yield 'bytes 15768749 15 04 mb' => [15768749, '15.04 MB'];
        yield 'bytes 7415768749 6 91 gb' => [7415768749, '6.91 GB'];
        yield 'bytes 7369137415768749 6702 19 tb' => [7369137415768749, '6702.19 TB'];
    }

    #[DataProvider('maxUploadSizeProvider')]
    public function testGetMaxUploadSize(
        string $uploadMaxFilesize,
        string $postMaxSize,
        ?int $maxSize,
        int $expected
    ): void {
        IniMock::withIniMock([
            'upload_max_filesize' => $uploadMaxFilesize,
            'post_max_size' => $postMaxSize,
        ]);

        $maxUploadSize = MemorySizeCalculator::getMaxUploadSize($maxSize);

        static::assertSame($expected, $maxUploadSize);

        IniMock::withIniMock([]);
    }

    public static function maxUploadSizeProvider(): \Generator
    {
        yield 'uploadMaxFilesize is 2M, postMaxSize is 4M, maxSize is null' => [
            'uploadMaxFilesize' => '2M',
            'postMaxSize' => '4M',
            'maxSize' => null,
            'expected' => 2 * 1024 * 1024,
        ];

        yield 'uploadMaxFilesize is 4M, postMaxSize is 2M, maxSize is null' => [
            'uploadMaxFilesize' => '4M',
            'postMaxSize' => '2M',
            'maxSize' => null,
            'expected' => 2 * 1024 * 1024,
        ];

        yield 'uploadMaxFilesize is 4M, postMaxSize is 4M, maxSize is null' => [
            'uploadMaxFilesize' => '4M',
            'postMaxSize' => '4M',
            'maxSize' => null,
            'expected' => 4 * 1024 * 1024,
        ];

        yield 'uploadMaxFilesize is 2M, postMaxSize is 4M, maxSize is 8M' => [
            'uploadMaxFilesize' => '2M',
            'postMaxSize' => '4M',
            'maxSize' => 8 * 1024 * 1024,
            'expected' => 2 * 1024 * 1024,
        ];

        yield 'uploadMaxFilesize is 4M, postMaxSize is 2M, maxSize is 8M' => [
            'uploadMaxFilesize' => '4M',
            'postMaxSize' => '2M',
            'maxSize' => 8 * 1024 * 1024,
            'expected' => 2 * 1024 * 1024,
        ];

        yield 'uploadMaxFilesize is 4M, postMaxSize is 4M, maxSize is 8M' => [
            'uploadMaxFilesize' => '4M',
            'postMaxSize' => '4M',
            'maxSize' => 8 * 1024 * 1024,
            'expected' => 4 * 1024 * 1024,
        ];

        yield 'uploadMaxFilesize is 4M, postMaxSize is 4M, maxSize is 4M' => [
            'uploadMaxFilesize' => '4M',
            'postMaxSize' => '4M',
            'maxSize' => 4 * 1024 * 1024,
            'expected' => 4 * 1024 * 1024,
        ];
    }
}
