<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
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

    public function testFormatsWithOnePrecisionDigitByDefault(): void
    {
        static::assertSame('1.6 MB', (new ByteFormatTwigFilter(new RequestStack()))->formatBytes(1_677_722, locale: 'en-GB'));
    }

    public function testUsesTheGivenPrecisionAndLocale(): void
    {
        static::assertSame('1,62 MB', (new ByteFormatTwigFilter(new RequestStack()))->formatBytes(1_700_000, 2, 'de-DE'));
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
