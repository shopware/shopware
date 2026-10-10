<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Asset;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Adapter\Asset\FallbackUrlPackage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(FallbackUrlPackage::class)]
class FallbackUrlPackageTest extends TestCase
{
    use EnvTestBehaviour;

    public function testCliFallbacksToAppUrl(): void
    {
        $url = $this->createPackage()->getUrl('test');

        static::assertSame(EnvironmentHelper::getVariable('APP_URL') . '/test', $url);
    }

    public function testCliUrlGiven(): void
    {
        $url = $this->createPackage('https://shopware.com')->getUrl('test');

        static::assertSame('https://shopware.com/test', $url);
    }

    public function testWebFallbackToRequest(): void
    {
        $this->setEnvVars(['HTTP_HOST' => 'test.de']);
        $url = $this->createPackage()->getUrl('test');

        static::assertSame('http://test.de/test', $url);
    }

    public function testGetFromRequestStack(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('https://test.de'));

        $url = $this->createPackage(requestStack: $requestStack)->getUrl('test');

        static::assertSame('https://test.de/test', $url);
    }

    public function testFallbackUrlFollowsTheCurrentRequestWhenPackageIsReused(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('https://first.example'));
        $package = $this->createPackage(requestStack: $requestStack);

        static::assertSame('https://first.example/test', $package->getUrl('test'));

        $requestStack->pop();
        $requestStack->push(Request::create('https://second.example'));

        static::assertSame('https://second.example/test', $package->getUrl('test'));
    }

    public function testExplicitAssetUrlIsPreservedWhenPackageIsReused(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('https://first.example'));
        $package = $this->createPackage('https://cdn.example', $requestStack);

        static::assertSame('https://cdn.example/test', $package->getUrl('test'));

        $requestStack->pop();
        $requestStack->push(Request::create('https://second.example'));

        static::assertSame('https://cdn.example/test', $package->getUrl('test'));
    }

    public function testFallbackUrlIncludesTheBasePathOfTheCurrentRequest(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('https://shop.example/subdir/index.php/', server: [
            'SCRIPT_FILENAME' => '/var/www/subdir/index.php',
            'SCRIPT_NAME' => '/subdir/index.php',
        ]));

        $url = $this->createPackage(requestStack: $requestStack)->getUrl('test');

        static::assertSame('https://shop.example/subdir/test', $url);
    }

    public function testOnlyEmptyBaseUrlsFallBackToTheCurrentRequest(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('https://first.example'));
        // UrlPackage picks the base URL by crc32 of the path: logo.png maps to index 0, style.css to index 1
        $package = new FallbackUrlPackage(['https://cdn.example', ''], new EmptyVersionStrategy(), $requestStack);

        static::assertSame('https://first.example/style.css', $package->getUrl('style.css'));

        $requestStack->pop();
        $requestStack->push(Request::create('https://second.example'));

        static::assertSame('https://cdn.example/logo.png', $package->getUrl('logo.png'));
        static::assertSame('https://second.example/style.css', $package->getUrl('style.css'));
    }

    private function createPackage(string $url = '', ?RequestStack $requestStack = null): FallbackUrlPackage
    {
        return new FallbackUrlPackage([$url], new EmptyVersionStrategy(), $requestStack);
    }
}
