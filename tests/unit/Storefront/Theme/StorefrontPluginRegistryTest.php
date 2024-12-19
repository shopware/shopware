<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\Bundle;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\AbstractStorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
#[CoversClass(StorefrontPluginRegistry::class)]
class StorefrontPluginRegistryTest extends TestCase
{
    public function testGetByTechnicalNameLoadsSingleApp(): void
    {
        $appLoader = $this->createMock(ActiveAppsLoader::class);
        $appLoader->expects(static::once())
            ->method('getActiveApps')
            ->willReturn([
                [
                    'name' => 'App1',
                    'path' => 'App1',
                    'author' => 'App1',
                    'selfManaged' => false,
                ],
                [
                    'name' => 'App2',
                    'path' => 'App2',
                    'author' => 'App2',
                    'selfManaged' => false,
                ],
            ]);

        $pluginFactory = $this->createMock(AbstractStorefrontPluginConfigurationFactory::class);

        $config = new StorefrontPluginConfiguration('App1');
        $pluginFactory->expects(static::once())
            ->method('createFromApp')
            ->with('App1', 'App1')
            ->willReturn($config);

        $registry = new StorefrontPluginRegistry(
            $this->createMock(KernelInterface::class),
            $pluginFactory,
            $appLoader
        );

        static::assertSame(
            $config,
            $registry->getByTechnicalName('App1')
        );
    }

    public function testGetByTechnicalNameLoadsSinglePlugin(): void
    {
        $pluginFactory = $this->createMock(AbstractStorefrontPluginConfigurationFactory::class);

        $config = new StorefrontPluginConfiguration('Plugin1');
        $bundle = new class extends Bundle {
            protected string $name = 'Plugin1';
        };

        $pluginFactory->expects(static::once())
            ->method('createFromBundle')
            ->with($bundle)
            ->willReturn($config);

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->expects(static::once())
            ->method('getBundles')
            ->willReturn([$bundle]);

        $registry = new StorefrontPluginRegistry(
            $kernel,
            $pluginFactory,
            $this->createMock(ActiveAppsLoader::class)
        );

        static::assertSame(
            $config,
            $registry->getByTechnicalName('Plugin1')
        );
    }

    public function testGetConfigurationsExcludesServices(): void
    {
        $appLoader = $this->createMock(ActiveAppsLoader::class);
        $appLoader->expects(static::once())
            ->method('getActiveApps')
            ->willReturn([
                [
                    'name' => 'App1',
                    'path' => 'App1',
                    'author' => 'App1',
                    'selfManaged' => false,
                ],
                [
                    'name' => 'App2',
                    'path' => 'App2',
                    'author' => 'App2',
                    'selfManaged' => true,
                ],
            ]);

        $pluginFactory = $this->createMock(AbstractStorefrontPluginConfigurationFactory::class);

        $config = new StorefrontPluginConfiguration('App1');
        $pluginFactory->expects(static::once())
            ->method('createFromApp')
            ->with('App1', 'App1')
            ->willReturn($config);

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->expects(static::once())
            ->method('getBundles')
            ->willReturn([]);

        $registry = new StorefrontPluginRegistry(
            $kernel,
            $pluginFactory,
            $appLoader
        );

        $configs = $registry->getConfigurations();

        static::assertCount(1, $configs);
        static::assertTrue($configs->has('App1'));
    }
}
