<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Twig\Extension\ByteFormatTwigFilter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ByteFormatTwigFilter::class)]
class ByteFormatTwigFilterTest extends TestCase
{
    public function testRegistersTheFilter(): void
    {
        $filters = (new ByteFormatTwigFilter(new RequestStack()))->getFilters();

        static::assertCount(1, $filters);
        static::assertSame('format_bytes', $filters[0]->getName());
    }

    #[DataProvider('bytesProvider')]
    public function testFormatBytes(int $bytes, int $precision, string $locale, string $expected): void
    {
        static::assertSame($expected, (new ByteFormatTwigFilter(new RequestStack()))->formatBytes($bytes, $precision, $locale));
    }

    /**
     * @return iterable<string, array{int, int, string, string}>
     */
    public static function bytesProvider(): iterable
    {
        yield 'zero' => [0, 1, 'en-GB', '0 B'];
        yield 'negative' => [-5, 1, 'en-GB', '0 B'];
        yield 'bytes' => [512, 1, 'en-GB', '512 B'];
        yield 'one kilobyte' => [1024, 1, 'en-GB', '1 KB'];
        yield 'kilobytes' => [26_726, 1, 'en-GB', '26.1 KB'];
        yield 'megabytes' => [1_677_722, 1, 'en-GB', '1.6 MB'];
        yield 'megabytes in German' => [1_677_722, 1, 'de-DE', '1,6 MB'];
        yield 'precision' => [1_677_722, 2, 'en-GB', '1.6 MB'];
        yield 'more precision' => [1_700_000, 2, 'en-GB', '1.62 MB'];
        yield 'no precision' => [1_700_000, 0, 'en-GB', '2 MB'];
        yield 'largest unit' => [2 * 1024 ** 5, 1, 'en-GB', '2,048 TB'];
    }

    public function testUsesTheLocaleOfTheRequest(): void
    {
        $request = new Request();
        $request->setLocale('de-DE');

        $requestStack = new RequestStack();
        $requestStack->push($request);

        static::assertSame('1,6 MB', (new ByteFormatTwigFilter($requestStack))->formatBytes(1_677_722));
    }
}
