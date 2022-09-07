<?php

namespace Shopware\Tests\Unit\Storefront\Theme\Twig;

use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginRegistryInterface;
use Shopware\Storefront\Theme\Twig\ThemeInheritanceBuilder;
use PHPUnit\Framework\TestCase;

class ThemeInheritanceBuilderTest extends TestCase {

    private ThemeInheritanceBuilder $builder;

    public function setUp(): void {
        $this->builder = new ThemeInheritanceBuilder(new TestStorefrontPluginRegistry(
            new StorefrontPluginConfigurationCollection([
                new StorefrontPluginConfiguration("Storefront")
            ])
        ));
    }

    public function testBuildPreservesThePluginOrder() {
        $result = $this->builder->build([
            "ExtensionPlugin" => [],
            "BasePlugin" => [],
            "Storefront" => [],
        ], [
            "Storefront" => []
        ]);


        static::assertSame([
            "ExtensionPlugin" => [],
            "BasePlugin" => [],
            "Storefront" => []
        ], $result);
	}
}

/** @internal */
class TestStorefrontPluginRegistry implements StorefrontPluginRegistryInterface {

    private StorefrontPluginConfigurationCollection $plugins;

    public function __construct(StorefrontPluginConfigurationCollection $plugins) {
        $this->plugins = $plugins;
    }

    public function getConfigurations(): StorefrontPluginConfigurationCollection {
        return $this->plugins;
    }
}
