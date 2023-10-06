<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Theme\ConfigLoader;

use League\Flysystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Storefront\Theme\ConfigLoader\DatabaseAvailableThemeProvider;
use Shopware\Storefront\Theme\ConfigLoader\DatabaseConfigLoader;
use Shopware\Storefront\Theme\ConfigLoader\StaticFileConfigDumper;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;

/**
 * @internal
 */
class StaticFileConfigDumperTest extends TestCase
{
    public function testDumping(): void
    {
        $loader = $this->createMock(DatabaseConfigLoader::class);
        $loader->method('load')->willReturn(new StorefrontPluginConfiguration('Test'));

        $fs = $this->createMock(Filesystem::class);
        $fs->expects(static::exactly(4))->method('write');

        $themeProvider = $this->createMock(DatabaseAvailableThemeProvider::class);
        $themeProvider->method('load')->willReturn(['test' => 'test']);

        $dumper = new StaticFileConfigDumper(
            $loader,
            $themeProvider,
            $fs
        );

        $dumper->dumpConfig(Context::createDefaultContext());
        $dumper->dumpConfigFromEvent();
    }
}
