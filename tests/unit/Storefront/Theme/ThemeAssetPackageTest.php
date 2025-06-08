<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Storefront\Theme\MD5ThemePathBuilder;
use Shopware\Storefront\Theme\ThemeAssetPackage;
use Symfony\Component\Asset\VersionStrategy\StaticVersionStrategy;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[CoversClass(ThemeAssetPackage::class)]
class ThemeAssetPackageTest extends TestCase
{
    #[DataProvider('urlCases')]
    public function testGetUrl(string $inputUrl, ?Request $request, string $expectedUrl): void
    {
        $requestStack = new RequestStack();

        if ($request instanceof Request) {
            $requestStack->push($request);
        }

        $themeAssetPackage = new ThemeAssetPackage(
            ['http://localhost'],
            new StaticVersionStrategy('v1'),
            $requestStack,
            new MD5ThemePathBuilder()
        );

        $actual = $themeAssetPackage->getUrl($inputUrl);

        static::assertSame($expectedUrl, $actual);
    }

    public static function urlCases(): \Generator
    {
        yield 'absolute url' => [
            'http://localhost/absolute/url',
            Request::create('http://localhost'),
            'http://localhost/absolute/url',
        ];

        yield 'url without storefront request attributes' => [
            'path/to/file',
            Request::create('http://localhost'),
            'http://localhost/path/to/file?v1',
        ];

        yield 'url without current request' => [
            'path/to/file',
            null,
            'http://localhost/path/to/file?v1',
        ];

        $request = Request::create('http://localhost');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, 'salesChannelId');

        yield 'Storefront without theme id' => [
            'path/to/file',
            $request,
            'http://localhost/path/to/file?v1',
        ];

        $request = Request::create('http://localhost');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, 'salesChannelId');
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_ID, 'themeId');

        yield 'theme path prefix is applied on storefront requests' => [
            'path/to/file',
            $request,
            'http://localhost/theme/5c7a1cfde64c7f4533daa5a0c06c0a39/path/to/file?v1',
        ];

        yield 'theme id prefix is applied on storefront requests for assets' => [
            'assets/path/to/file',
            $request,
            'http://localhost/theme/themeId/assets/path/to/file?v1',
        ];
    }
}
