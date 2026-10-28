<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Asset;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Asset\BundleAssetPackage;
use Shopware\Core\Framework\Adapter\Asset\FallbackUrlPackage;
use Shopware\Core\Framework\Adapter\Asset\PrefixVersionStrategy;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Asset\Exception\InvalidArgumentException;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\Asset\VersionStrategy\StaticVersionStrategy;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(BundleAssetPackage::class)]
class BundleAssetPackageTest extends TestCase
{
    public function testFollowsTheCurrentRequestWhenReused(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('https://first.example'));
        $package = $this->createBundlePackage('', $requestStack);

        static::assertSame('https://first.example/bundles/test/foo.js', $package->getUrl('foo.js'));

        $requestStack->pop();
        $requestStack->push(Request::create('https://second.example'));

        static::assertSame('https://second.example/bundles/test/foo.js', $package->getUrl('foo.js'));
    }

    public function testKeepsAConfiguredAssetUrl(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('https://first.example'));
        $package = $this->createBundlePackage('https://cdn.example', $requestStack);

        $requestStack->pop();
        $requestStack->push(Request::create('https://second.example'));

        static::assertSame('https://cdn.example/bundles/test/foo.js', $package->getUrl('foo.js'));
    }

    public function testAppliesTheVersionStrategy(): void
    {
        $package = new BundleAssetPackage(
            new FallbackUrlPackage('https://cdn.example', new EmptyVersionStrategy()),
            '/bundles/test',
            new PrefixVersionStrategy('/bundles/test', new StaticVersionStrategy('v1', '%s?%s')),
        );

        static::assertSame('https://cdn.example/bundles/test/foo.js?v1', $package->getUrl('foo.js'));
    }

    public function testRejectsAnInvalidAssetUrlOnCreation(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BundleAssetPackage(
            new FallbackUrlPackage('/relative', new EmptyVersionStrategy()),
            '/bundles/test',
            new EmptyVersionStrategy(),
        );
    }

    private function createBundlePackage(string $assetUrl, RequestStack $requestStack): BundleAssetPackage
    {
        return new BundleAssetPackage(
            new FallbackUrlPackage($assetUrl, new EmptyVersionStrategy(), $requestStack),
            '/bundles/test',
            new PrefixVersionStrategy('/bundles/test', new EmptyVersionStrategy()),
        );
    }
}
