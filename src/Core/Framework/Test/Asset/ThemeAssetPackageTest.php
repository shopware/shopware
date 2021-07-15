<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Asset;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Asset\ThemeAssetPackage;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class ThemeAssetPackageTest extends TestCase
{
    public function testEmptyStack(): void
    {
        $asset = new ThemeAssetPackage(['http://localhost'], new EmptyVersionStrategy(), new RequestStack());

        static::assertSame('http://localhost/all.js', $asset->getUrl('/all.js'));
    }

    public function testAdminRequest(): void
    {
        $request = new Request();
        $stack = new RequestStack();
        $stack->push($request);

        $asset = new ThemeAssetPackage(['http://localhost'], new EmptyVersionStrategy(), $stack);

        static::assertSame('http://localhost/all.js', $asset->getUrl('/all.js'));
    }

    public function testStorefrontWithoutThemeId(): void
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, 'abc');
        $stack = new RequestStack();
        $stack->push($request);

        $asset = new ThemeAssetPackage(['http://localhost'], new EmptyVersionStrategy(), $stack);

        static::assertSame('http://localhost/all.js', $asset->getUrl('/all.js'));
    }

    public function testStorefrontAllConditionsMatching(): void
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, 'abc');
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_ID, 'abc');
        $stack = new RequestStack();
        $stack->push($request);

        $asset = new ThemeAssetPackage(['http://localhost'], new EmptyVersionStrategy(), $stack);

        static::assertSame('http://localhost/theme/440ac85892ca43ad26d44c7ad9d47d3e/all.js', $asset->getUrl('/all.js'));
    }

    public function testVersioningStrategyReceivesRelativeFilePath(): void
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, 'abc');
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_ID, 'abc');
        $stack = new RequestStack();
        $stack->push($request);

        $versionStrategyMock = $this->createMock(VersionStrategyInterface::class);
        $versionStrategyMock->expects(static::once())
            ->method('applyVersion')
            ->with('/theme/440ac85892ca43ad26d44c7ad9d47d3e/all.js')
            ->willReturn('/theme/440ac85892ca43ad26d44c7ad9d47d3e/all.js?abc');

        $asset = new ThemeAssetPackage(['http://localhost'], $versionStrategyMock, $stack);

        static::assertSame('http://localhost/theme/440ac85892ca43ad26d44c7ad9d47d3e/all.js?abc', $asset->getUrl('/all.js'));
    }

    public function testStorefrontAllConditionsMatchingFallback(): void
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, 'abc');
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_ID, 'abc');
        $stack = new RequestStack();
        $stack->push($request);

        $asset = new ThemeAssetPackage(['http://localhost'], new EmptyVersionStrategy(), $stack);

        static::assertSame('http://localhost/bundles/foo/test.js', $asset->getUrl('/bundles/foo/test.js'));
    }
}
