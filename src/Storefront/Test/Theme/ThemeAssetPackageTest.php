<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Theme;

use PHPUnit\Framework\TestCase;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Storefront\Theme\MD5ThemePathBuilder;
use Shopware\Storefront\Theme\ThemeAssetPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
class ThemeAssetPackageTest extends TestCase
{
    public function testEmptyStack(): void
    {
        $asset = new ThemeAssetPackage(['http://localhost'], new EmptyVersionStrategy(), new RequestStack(), new MD5ThemePathBuilder());

        static::assertSame('http://localhost/all.js', $asset->getUrl('/all.js'));
    }

    public function testAdminRequest(): void
    {
        $request = new Request();
        $stack = new RequestStack();
        $stack->push($request);

        $asset = new ThemeAssetPackage(['http://localhost'], new EmptyVersionStrategy(), $stack, new MD5ThemePathBuilder());

        static::assertSame('http://localhost/all.js', $asset->getUrl('/all.js'));
    }

    public function testStorefrontWithoutThemeId(): void
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, 'abc');
        $stack = new RequestStack();
        $stack->push($request);

        $asset = new ThemeAssetPackage(['http://localhost'], new EmptyVersionStrategy(), $stack, new MD5ThemePathBuilder());

        static::assertSame('http://localhost/all.js', $asset->getUrl('/all.js'));
    }

    public function testStorefrontAllConditionsMatching(): void
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, 'abc');
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_ID, 'abc');
        $stack = new RequestStack();
        $stack->push($request);

        $asset = new ThemeAssetPackage(['http://localhost'], new EmptyVersionStrategy(), $stack, new MD5ThemePathBuilder());

        static::assertSame('http://localhost/theme/440ac85892ca43ad26d44c7ad9d47d3e/all.js', $asset->getUrl('/all.js'));
    }
}
