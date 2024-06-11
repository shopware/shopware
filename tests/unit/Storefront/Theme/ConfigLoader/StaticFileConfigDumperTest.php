<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\ConfigLoader;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Storefront\Theme\ConfigLoader\DatabaseAvailableThemeProvider;
use Shopware\Storefront\Theme\ConfigLoader\DatabaseConfigLoader;
use Shopware\Storefront\Theme\ConfigLoader\StaticFileAvailableThemeProvider;
use Shopware\Storefront\Theme\ConfigLoader\StaticFileConfigDumper;
use Shopware\Storefront\Theme\Event\ThemeAssignedEvent;
use Shopware\Storefront\Theme\Event\ThemeConfigChangedEvent;
use Shopware\Storefront\Theme\Event\ThemeConfigResetEvent;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;

/**
 * @internal
 */
#[CoversClass(StaticFileConfigDumper::class)]
class StaticFileConfigDumperTest extends TestCase
{
    public function testDumping(): void
    {
        $salesChannelToTheme = new StorefrontPluginConfiguration('Test');
        $loader = $this->createMock(DatabaseConfigLoader::class);
        $loader->method('load')->willReturn($salesChannelToTheme);

        $fs = new Filesystem(new InMemoryFilesystemAdapter());

        $themeProvider = $this->createMock(DatabaseAvailableThemeProvider::class);
        $themeProvider->method('load')->willReturn(['test' => 'test']);

        $dumper = new StaticFileConfigDumper(
            $loader,
            $themeProvider,
            $fs
        );

        $location = StaticFileAvailableThemeProvider::THEME_INDEX;

        $dumper->dumpConfig(Context::createDefaultContext());
        static::assertEquals('{"test":"test"}', $fs->read($location));

        $dumper->dumpConfigFromEvent();
        static::assertEquals('{"test":"test"}', $fs->read($location));
    }

    public function testgetSubscribedEvents(): void
    {
        static::assertEquals(
            [
                ThemeConfigChangedEvent::class => 'dumpConfigFromEvent',
                ThemeAssignedEvent::class => 'dumpConfigFromEvent',
                ThemeConfigResetEvent::class => 'dumpConfigFromEvent',
            ],
            StaticFileConfigDumper::getSubscribedEvents()
        );
    }
}
