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
        yield 'compact kilobyte value is parsed as bytes' => ['2k', 2048];
        yield 'spaced kilobyte value is parsed as bytes' => ['2 k', 2048];
        yield 'megabyte value is parsed as bytes' => ['8m', 8 * 1024 * 1024];
        yield 'signed kilobyte value is parsed as bytes' => ['+2 k', 2048];
        yield 'unit after invalid characters is still parsed as kilobytes' => ['+2???k', 2048];
        yield 'hexadecimal value is parsed as bytes' => ['0x10', 16];
        yield 'lower hexadecimal value is parsed as bytes' => ['0xf', 15];
        yield 'octal value is parsed as bytes' => ['010', 8];
        yield 'signed hexadecimal kilobyte value is parsed as bytes' => ['+0x10 k', 16 * 1024];
        yield 'compact gigabyte value is parsed as bytes' => ['1g', 1024 * 1024 * 1024];
        yield 'uppercase gigabyte value is parsed as bytes' => ['1G', 1024 * 1024 * 1024];
        yield 'unlimited memory value is kept as negative one' => ['-1', -1];
        yield 'zero memory value is parsed as zero bytes' => ['0', 0];
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
        yield 'zero bytes are formatted as bytes' => [0, '0 B'];
        yield 'small value is formatted as bytes' => [100, '100 B'];
        yield 'one kilobyte is formatted without decimals' => [1024, '1 KB'];
        yield 'kilobyte value is formatted with decimals' => [2024, '1.98 KB'];
        yield 'larger kilobyte value is formatted with decimals' => [20240, '19.77 KB'];
        yield 'megabyte value is formatted with decimals' => [15768749, '15.04 MB'];
        yield 'gigabyte value is formatted with decimals' => [7415768749, '6.91 GB'];
        yield 'terabyte value is formatted with decimals' => [7369137415768749, '6702.19 TB'];
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
