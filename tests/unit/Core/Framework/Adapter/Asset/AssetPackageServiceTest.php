<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Asset;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Asset\AssetPackageService;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;

/**
 * @internal
 */
#[CoversClass(AssetPackageService::class)]
class AssetPackageServiceTest extends TestCase
{
    public function testCreate(): void
    {
        $bundleMap = [
            'TestBundle' => '/var/www/html/vendor/shopware/core/TestBundle',
            'TestPlugin' => '/var/www/html/custom/plugins/TestPlugin',
        ];

        $package = $this->createMock(Package::class);
        $package->method('getUrl')
            ->willReturnCallback(static function (string $path): string {
                $urls = [
                    // bundle prefix should be removed @see AssetService::getTargetDirectory
                    // and the path should be lowercased
                    '/bundles/test' => 'http://localhost/bundles/test',
                    '/bundles/testplugin' => 'http://localhost/bundles/testplugin',
                ];

                return $urls[$path];
            });

        $defaultPackage = new Package(new EmptyVersionStrategy());
        $packages = AssetPackageService::create($bundleMap, $package, new EmptyVersionStrategy(), $defaultPackage);

        static::assertSame($defaultPackage, $packages->getPackage());

        $bundlePackage = $packages->getPackage('@TestBundle');
        static::assertInstanceOf(UrlPackage::class, $bundlePackage);
        static::assertSame('http://localhost/bundles/test/foo', $bundlePackage->getUrl('/foo'));

        $pluginPackage = $packages->getPackage('@TestPlugin');
        static::assertInstanceOf(UrlPackage::class, $pluginPackage);
        static::assertSame('http://localhost/bundles/testplugin/foo', $pluginPackage->getUrl('/foo'));
    }
}
