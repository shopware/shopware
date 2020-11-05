<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Theme;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Storefront;
use Shopware\Storefront\Test\Theme\fixtures\ConfigWithoutStorefrontDefined\ConfigWithoutStorefrontDefined;
use Shopware\Storefront\Test\Theme\fixtures\InheritanceWithConfig\InheritanceWithConfig;
use Shopware\Storefront\Test\Theme\fixtures\PluginWildcardAndExplicit\PluginWildcardAndExplicit;
use Shopware\Storefront\Test\Theme\fixtures\SimplePlugin\SimplePlugin;
use Shopware\Storefront\Test\Theme\fixtures\SimpleTheme\SimpleTheme;
use Shopware\Storefront\Test\Theme\fixtures\ThemeWithMultiInheritance\ThemeWithMultiInheritance;
use Shopware\Storefront\Test\Theme\fixtures\ThemeWithoutStorefront\ThemeWithoutStorefront;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\StorefrontPluginRegistryInterface;
use Shopware\Storefront\Theme\Twig\ThemeInheritanceBuilder;

class ThemeInheritanceBuilderTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var StorefrontPluginRegistryInterface
     */
    private $themeRegistryMock;

    /**
     * @var ThemeInheritanceBuilder
     */
    private $builder;

    /**
     * @var StorefrontPluginConfigurationFactory
     */
    private $configFactory;

    public function setUp(): void
    {
        $this->themeRegistryMock = $this->createMock(StorefrontPluginRegistry::class);

        $this->builder = new ThemeInheritanceBuilder($this->themeRegistryMock);

        $this->configFactory = $this->getContainer()->get(StorefrontPluginConfigurationFactory::class);
    }

    public function testInheritanceWithConfig(): void
    {
        $configs = new StorefrontPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Storefront()),
            $this->configFactory->createFromBundle(new InheritanceWithConfig()),
        ]);

        $this->themeRegistryMock->method('getConfigurations')
            ->willReturn($configs);

        $inheritance = $this->builder->build(
            ['InheritanceWithConfig', 'Storefront'],
            ['InheritanceWithConfig' => true, 'Storefront' => true]
        );

        static::assertEquals(['InheritanceWithConfig', 'Storefront'], $inheritance);
    }

    public function testEnsurePlugins(): void
    {
        $configs = new StorefrontPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Storefront()),
            $this->configFactory->createFromBundle(new InheritanceWithConfig()),
            $this->configFactory->createFromBundle($this->getMockedBundle('PayPal', SimplePlugin::class)),
        ]);

        $this->themeRegistryMock->method('getConfigurations')
            ->willReturn($configs);

        $inheritance = $this->builder->build(
            ['InheritanceWithConfig', 'Storefront', 'PayPal'],
            ['InheritanceWithConfig' => true, 'Storefront' => true]
        );

        static::assertEquals(['PayPal', 'InheritanceWithConfig', 'Storefront'], $inheritance);
    }

    public function testConfigWithoutStorefrontDefined(): void
    {
        $configs = new StorefrontPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Storefront()),
            $this->configFactory->createFromBundle(new ConfigWithoutStorefrontDefined()),
            $this->configFactory->createFromBundle($this->getMockedBundle('PayPal', SimplePlugin::class)),
        ]);

        $this->themeRegistryMock->method('getConfigurations')
            ->willReturn($configs);

        $inheritance = $this->builder->build(
            ['ConfigWithoutStorefrontDefined', 'Storefront', 'PayPal'],
            ['ConfigWithoutStorefrontDefined' => true]
        );

        static::assertEquals(['PayPal', 'ConfigWithoutStorefrontDefined'], $inheritance);
    }

    public function testPluginWildcardAndExplicit(): void
    {
        $configs = new StorefrontPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Storefront()),
            $this->configFactory->createFromBundle(new PluginWildcardAndExplicit()),
            $this->configFactory->createFromBundle($this->getMockedBundle('PayPal', SimplePlugin::class)),
            $this->configFactory->createFromBundle($this->getMockedBundle('CustomProducts', SimplePlugin::class)),
        ]);

        $this->themeRegistryMock->method('getConfigurations')
            ->willReturn($configs);

        $inheritance = $this->builder->build(
            ['PluginWildcardAndExplicit', 'Storefront', 'PayPal', 'CustomProducts'],
            ['PluginWildcardAndExplicit' => true, 'Storefront' => true]
        );

        static::assertEquals(['CustomProducts', 'PluginWildcardAndExplicit', 'PayPal', 'Storefront'], $inheritance);
    }

    public function testThemeWithoutStorefront(): void
    {
        $configs = new StorefrontPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Storefront()),
            $this->configFactory->createFromBundle(new ThemeWithoutStorefront()),
            $this->configFactory->createFromBundle($this->getMockedBundle('PayPal', SimplePlugin::class)),
            $this->configFactory->createFromBundle($this->getMockedBundle('CustomProducts', SimplePlugin::class)),
        ]);

        $this->themeRegistryMock->method('getConfigurations')
            ->willReturn($configs);

        $inheritance = $this->builder->build(
            ['ThemeWithoutStorefront', 'Storefront', 'PayPal', 'CustomProducts'],
            ['ThemeWithoutStorefront' => true, 'Storefront' => true]
        );

        static::assertEquals(['CustomProducts', 'ThemeWithoutStorefront', 'PayPal'], $inheritance);
    }

    public function testMultiInheritance(): void
    {
        $configs = new StorefrontPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Storefront()),
            $this->configFactory->createFromBundle(new ThemeWithMultiInheritance()),
            $this->configFactory->createFromBundle($this->getMockedBundle('ThemeA', SimpleTheme::class)),
            $this->configFactory->createFromBundle($this->getMockedBundle('ThemeB', SimpleTheme::class)),
            $this->configFactory->createFromBundle($this->getMockedBundle('ThemeC', SimpleTheme::class)),

            // paypal is a plugin and should be registered
            $this->configFactory->createFromBundle($this->getMockedBundle('PayPal', SimplePlugin::class)),

            // theme d is not included in theme.json
            $this->configFactory->createFromBundle($this->getMockedBundle('ThemeD', SimpleTheme::class)),
        ]);

        $this->themeRegistryMock->method('getConfigurations')
            ->willReturn($configs);

        $inheritance = $this->builder->build(
            ['ThemeWithMultiInheritance', 'ThemeA', 'ThemeB', 'ThemeC', 'ThemeD', 'PayPal'],
            ['ThemeWithMultiInheritance' => true]
        );

        static::assertEquals(
            ['ThemeWithMultiInheritance', 'ThemeC', 'PayPal', 'ThemeB', 'ThemeA'],
            $inheritance
        );
    }

    private function getMockedBundle(string $bundleName, string $bundleClass): Bundle
    {
        $bundle = new $bundleClass();

        $reflection = new \ReflectionClass($bundleClass);
        $name = $reflection->getProperty('name');
        $name->setAccessible(true);
        $name->setValue($bundle, $bundleName);

        return $bundle;
    }
}
