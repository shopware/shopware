<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Asset;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Asset\FallbackUrlPackage;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(FallbackUrlPackage::class)]
class FallbackUrlPackageTest extends TestCase
{
    public function testCliFallbacksToAppUrl(): void
    {
        $package = new FallbackUrlPackage([''], new EmptyVersionStrategy());
        $url = $package->getUrl('test');

        static::assertSame($_SERVER['APP_URL'] . '/test', $url);
    }

    public function testCliUrlGiven(): void
    {
        $package = new FallbackUrlPackage(['http://shopware.com'], new EmptyVersionStrategy());
        $url = $package->getUrl('test');

        static::assertSame('http://shopware.com/test', $url);
    }

    public function testWebFallbackToRequest(): void
    {
        $_SERVER['HTTP_HOST'] = 'test.de';
        $package = new FallbackUrlPackage([''], new EmptyVersionStrategy());
        $url = $package->getUrl('test');

        static::assertSame('http://test.de/test', $url);
        unset($_SERVER['HTTP_HOST']);
    }

    public function testGetFromRequestStack(): void
    {
        $stack = new RequestStack();
        $request = new Request();
        $request->headers->set('HOST', 'test.de');
        $stack->push($request);

        $package = new FallbackUrlPackage([''], new EmptyVersionStrategy(), $stack);

        $url = $package->getUrl('test');

        static::assertSame('http://test.de/test', $url);
    }
}
