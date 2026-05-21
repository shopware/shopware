<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Ucp\UcpVersion;

/**
 * @internal
 */
#[CoversClass(UcpVersion::class)]
class UcpVersionTest extends TestCase
{
    public function testCurrentIsValidFormat(): void
    {
        static::assertTrue(UcpVersion::isValidFormat(UcpVersion::CURRENT));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideFormats(): iterable
    {
        yield 'good 2026-01-23' => ['2026-01-23', true];
        yield 'good 1999-12-31' => ['1999-12-31', true];
        yield 'bad draft' => ['draft', false];
        yield 'bad short' => ['2026-1-1', false];
        yield 'bad long' => ['2026-01-235', false];
        yield 'bad slashes' => ['2026/01/23', false];
        yield 'empty' => ['', false];
    }

    #[DataProvider('provideFormats')]
    public function testIsValidFormat(string $input, bool $expected): void
    {
        static::assertSame($expected, UcpVersion::isValidFormat($input));
    }

    public function testCompareOrdersChronologically(): void
    {
        static::assertSame(-1, UcpVersion::compare('2026-01-11', '2026-01-23'));
        static::assertSame(0, UcpVersion::compare('2026-01-23', '2026-01-23'));
        static::assertSame(1, UcpVersion::compare('2026-01-23', '2026-01-11'));
    }
}
