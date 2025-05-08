<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Asset;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Adapter\Asset\AssetPackageService;
use Shopware\Core\Framework\Adapter\Asset\FallbackUrlPackage;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[CoversClass(AssetPackageService::class)]
class AssetPackageServiceTest extends TestCase
{
    use EnvTestBehaviour;

    public function testCreateWithRequest(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('https://test.de'));

        $packages = $this->getPackages($requestStack);

        $bundlePackage = $packages->getPackage('@TestBundle');
        static::assertInstanceOf(UrlPackage::class, $bundlePackage);
        static::assertSame('https://test.de/bundles/test/foo', $bundlePackage->getUrl('/foo'));

        $pluginPackage = $packages->getPackage('@TestPlugin');
        static::assertInstanceOf(UrlPackage::class, $pluginPackage);
        static::assertSame('https://test.de/bundles/testplugin/foo', $pluginPackage->getUrl('/foo'));
    }

    public function testCreateWithAppUrl(): void
    {
        $this->setEnvVars(['APP_URL' => 'https://test.de']);

        $packages = $this->getPackages();

        $bundlePackage = $packages->getPackage('@TestBundle');
        static::assertInstanceOf(UrlPackage::class, $bundlePackage);
        static::assertSame('https://test.de/bundles/test/foo', $bundlePackage->getUrl('/foo'));

        $pluginPackage = $packages->getPackage('@TestPlugin');
        static::assertInstanceOf(UrlPackage::class, $pluginPackage);
        static::assertSame('https://test.de/bundles/testplugin/foo', $pluginPackage->getUrl('/foo'));
    }

    public function testCreateWithoutAppUrl(): void
    {
        $this->setEnvVars(['APP_URL' => '']);
        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage('Invalid asset URL. Check the "APP_URL" environment variable. Error message: "/bundles/test" is not a valid URL.');
        $this->getPackages();
    }

    private function getPackages(?RequestStack $requestStack = null): Packages
    {
        $emptyVersionStrategy = new EmptyVersionStrategy();

        return AssetPackageService::create(
            [
                'TestBundle' => '/var/www/html/vendor/shopware/core/TestBundle',
                'TestPlugin' => '/var/www/html/custom/plugins/TestPlugin',
            ],
            new FallbackUrlPackage('', $emptyVersionStrategy, $requestStack),
            $emptyVersionStrategy,
            new Package($emptyVersionStrategy)
        );
    }
}
