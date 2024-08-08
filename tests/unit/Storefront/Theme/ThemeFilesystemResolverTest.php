<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Kernel;
use Shopware\Core\Test\Stub\App\StaticSourceResolver;
use Shopware\Core\Test\Stub\Framework\Util\StaticFilesystem;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\ThemeFilesystemResolver;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\MockStorefront\MockStorefront;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

/**
 * @internal
 */
#[CoversClass(ThemeFilesystemResolver::class)]
class ThemeFilesystemResolverTest extends TestCase
{
    public function testGetFilesystemForStorefrontUsesBundleRootWithoutResourcePrefix(): void
    {
        $kernel = $this->createMock(Kernel::class);
        $bundle = new MockStorefront();
        $kernel->expects(static::once())->method('getBundles')->willReturn([
            'Storefront' => $bundle,
        ]);

        $kernel->expects(static::once())->method('getBundle')->willReturnMap([
            ['Storefront', $bundle],
        ]);

        $resolver = new ThemeFilesystemResolver(
            new StaticSourceResolver(),
            $kernel
        );

        $pluginConfig = new StorefrontPluginConfiguration('Storefront');
        $fs = $resolver->getFilesystemForStorefrontConfig($pluginConfig);

        static::assertEquals($bundle->getPath(), $fs->location);
    }

    public function testGetFilesystemDelegatesToAppSourceResolverForApps(): void
    {
        $resolver = new ThemeFilesystemResolver(
            new StaticSourceResolver([
                'CoolApp' => new StaticFilesystem(),
            ]),
            $this->createMock(Kernel::class)
        );

        $pluginConfig = new StorefrontPluginConfiguration('CoolApp');

        $fs = $resolver->getFilesystemForStorefrontConfig($pluginConfig);

        static::assertEquals('/app-root', $fs->location);
    }

    public function testGetFilesystemForPluginUsesBundleBasePath(): void
    {
        $kernel = $this->createMock(Kernel::class);
        $bundle = $this->createMock(BundleInterface::class);
        $bundle->expects(static::once())->method('getPath')->willReturn('/some/project/custom/plugins/CoolPlugin');
        $kernel->expects(static::once())->method('getBundles')->willReturn([
            'CoolPlugin' => $bundle,
        ]);

        $kernel->expects(static::once())->method('getBundle')->willReturnMap([
            ['CoolPlugin', $bundle],
        ]);

        $resolver = new ThemeFilesystemResolver(
            new StaticSourceResolver(),
            $kernel
        );

        $pluginConfig = new StorefrontPluginConfiguration('CoolPlugin');

        $fs = $resolver->getFilesystemForStorefrontConfig($pluginConfig);

        static::assertEquals('/some/project/custom/plugins/CoolPlugin', $fs->location);
    }
}
