<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Kernel;
use Shopware\Core\Test\Stub\App\StaticSourceResolver;
use Shopware\Core\Test\Stub\Framework\Util\StaticFilesystem;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\ThemeFilesystemResolver;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

/**
 * @internal
 */
#[CoversClass(ThemeFilesystemResolver::class)]
class ThemeFilesystemResolverTest extends TestCase
{
    public function testGetFilesystemForStorefrontUsesRepositoryRootWithoutResourcePrefix(): void
    {
        $kernel = $this->createMock(Kernel::class);
        $kernel->expects(static::once())->method('getBundles')->willReturn([
            'Storefront' => $this->createMock(BundleInterface::class),
        ]);

        $resolver = new ThemeFilesystemResolver(
            new StaticSourceResolver(),
            __DIR__,
            $kernel
        );

        $pluginConfig = new StorefrontPluginConfiguration('Storefront');
        $pluginConfig->setBasePath('src/Storefront/Resources');

        $fs = $resolver->getFilesystemForStorefrontConfig($pluginConfig);

        static::assertEquals(__DIR__ . '/src/Storefront', $fs->location);
    }

    public function testGetFilesystemDelegatesToAppSourceResolverForApps(): void
    {
        $resolver = new ThemeFilesystemResolver(
            new StaticSourceResolver([
                'CoolApp' => new StaticFilesystem(),
            ]),
            __DIR__,
            $this->createMock(Kernel::class)
        );

        $pluginConfig = new StorefrontPluginConfiguration('CoolApp');

        $fs = $resolver->getFilesystemForStorefrontConfig($pluginConfig);

        static::assertEquals('/app-root', $fs->location);
    }

    public function testGetFilesystemForPluginUsesBasePathForPlugins(): void
    {
        $kernel = $this->createMock(Kernel::class);
        $kernel->expects(static::once())->method('getBundles')->willReturn([
            'CoolPlugin' => $this->createMock(BundleInterface::class),
        ]);

        $resolver = new ThemeFilesystemResolver(
            new StaticSourceResolver(),
            __DIR__,
            $kernel
        );

        $pluginConfig = new StorefrontPluginConfiguration('CoolPlugin');
        $pluginConfig->setBasePath('custom/plugins/CoolPlugin');

        $fs = $resolver->getFilesystemForStorefrontConfig($pluginConfig);

        static::assertEquals(__DIR__ . '/custom/plugins/CoolPlugin', $fs->location);
    }
}
