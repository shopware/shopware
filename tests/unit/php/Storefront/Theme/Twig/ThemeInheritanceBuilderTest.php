<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Twig;

use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginRegistryInterface;
use Shopware\Storefront\Theme\Twig\ThemeInheritanceBuilder;

/**
 * @internal
 *
 * @covers \Shopware\Storefront\Theme\Twig\ThemeInheritanceBuilder
 */
class ThemeInheritanceBuilderTest extends TestCase
{
    private ThemeInheritanceBuilder $builder;

    public function setUp(): void
    {
        $this->builder = new ThemeInheritanceBuilder(new TestStorefrontPluginRegistry(
            new StorefrontPluginConfigurationCollection([
                new StorefrontPluginConfiguration('Storefront'),
            ])
        ));
    }

    public function testBuildPreservesThePluginOrder(): void
    {
        $result = $this->builder->build([
            'ExtensionPlugin' => [],
            'BasePlugin' => [],
            'Storefront' => [],
        ], [
            'Storefront' => [],
        ]);

        static::assertSame([
            'ExtensionPlugin' => [],
            'BasePlugin' => [],
            'Storefront' => [],
        ], $result);
    }
}

/**
 * @internal
 */
class TestStorefrontPluginRegistry implements StorefrontPluginRegistryInterface
{
    public function __construct(private readonly StorefrontPluginConfigurationCollection $plugins)
    {
    }

    public function getConfigurations(): StorefrontPluginConfigurationCollection
    {
        return $this->plugins;
    }
}
