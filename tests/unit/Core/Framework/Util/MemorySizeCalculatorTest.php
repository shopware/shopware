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
        static::assertEquals($bytes, MemorySizeCalculator::convertToBytes($limit));
    }

    /**
     * We are trying to replicate the Symfony's convertToBytes method. Therefore, we will use the test cases Symfony
     * uses.
     *
     * See also:
     * https://github.com/symfony/symfony/blob/3a96e4cde6aa0c9e138bdfcce60564a2f396c070/src/Symfony/Component/HttpKernel/Tests/DataCollector/MemoryDataCollectorTest.php
     *
     * @return array{0: string, 1: int}[]
     */
    public static function memorySizeDataProvider(): array
    {
        return [
            ['2k', 2048],
            ['2 k', 2048],
            ['8m', 8 * 1024 * 1024],
            ['+2 k', 2048],
            ['+2???k', 2048],
            ['0x10', 16],
            ['0xf', 15],
            ['010', 8],
            ['+0x10 k', 16 * 1024],
            ['1g', 1024 * 1024 * 1024],
            ['1G', 1024 * 1024 * 1024],
            ['-1', -1],
            ['0', 0],
            ['2mk', 2048], // the unit must be the last char, so in this case 'k', not 'm'
        ];
    }

    #[DataProvider('bytesProvider')]
    public function testFormatBytes(int $bytes, string $formatted): void
    {
        static::assertEquals($formatted, MemorySizeCalculator::formatToBytes($bytes));
    }

    /**
     * @return array<array{0: int, 1: string}>
     */
    public static function bytesProvider(): array
    {
        return [
            [0, '0 B'],
            [100, '100 B'],
            [1024, '1 KB'],
            [2024, '1.98 KB'],
            [20240, '19.77 KB'],
            [15768749, '15.04 MB'],
            [7415768749, '6.91 GB'],
            [7369137415768749, '6702.19 TB'],
        ];
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

        static::assertEquals($expected, $maxUploadSize);

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
