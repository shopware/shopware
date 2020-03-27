<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Theme;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Kernel;
use Shopware\Storefront\Storefront;
use Shopware\Storefront\Test\Theme\fixtures\ConfigWithoutStorefrontDefined\ConfigWithoutStorefrontDefined;
use Shopware\Storefront\Test\Theme\fixtures\InheritanceWithConfig\InheritanceWithConfig;
use Shopware\Storefront\Test\Theme\fixtures\PluginWildcardAndExplicit\PluginWildcardAndExplicit;
use Shopware\Storefront\Test\Theme\fixtures\SimpleTheme\SimpleTheme;
use Shopware\Storefront\Test\Theme\fixtures\ThemeWithMultiInheritance\ThemeWithMultiInheritance;
use Shopware\Storefront\Test\Theme\fixtures\ThemeWithoutConfig\ThemeWithoutConfig;
use Shopware\Storefront\Test\Theme\fixtures\ThemeWithoutStorefront\ThemeWithoutStorefront;
use Shopware\Storefront\Theme\Twig\ThemeInheritanceBuilder;

class ThemeInheritanceBuilderTest extends TestCase
{
    public function testInheritanceWithoutConfig(): void
    {
        $kernel = new MockedKernel([
            'ThemeWithoutConfig' => new ThemeWithoutConfig(),
            'Storefront' => new Storefront(),
        ]);

        $builder = new ThemeInheritanceBuilder($kernel);

        $inheritance = $builder->build(
            ['ThemeWithoutConfig', 'Storefront'],
            ['ThemeWithoutConfig' => true, 'Storefront' => true]
        );

        static::assertEquals(['ThemeWithoutConfig', 'Storefront'], $inheritance);
    }

    public function testInheritanceWithConfig(): void
    {
        $kernel = new MockedKernel([
            'ThemeWithoutConfig' => new ThemeWithoutConfig(),
            'InheritanceWithConfig' => new InheritanceWithConfig(),
            'Storefront' => new Storefront(),
        ]);

        $builder = new ThemeInheritanceBuilder($kernel);

        $inheritance = $builder->build(
            ['InheritanceWithConfig', 'Storefront'],
            ['InheritanceWithConfig' => true, 'Storefront' => true]
        );

        static::assertEquals(['InheritanceWithConfig', 'Storefront'], $inheritance);
    }

    public function testEnsurePlugins(): void
    {
        $kernel = new MockedKernel([
            'ThemeWithoutConfig' => new ThemeWithoutConfig(),
            'InheritanceWithConfig' => new InheritanceWithConfig(),
            'Storefront' => new Storefront(),
            'PayPal' => $this->createMock(Bundle::class),
        ]);

        $builder = new ThemeInheritanceBuilder($kernel);

        $inheritance = $builder->build(
            ['InheritanceWithConfig', 'Storefront', 'PayPal'],
            ['InheritanceWithConfig' => true, 'Storefront' => true]
        );

        static::assertEquals(['PayPal', 'InheritanceWithConfig', 'Storefront'], $inheritance);
    }

    public function testConfigWithoutStorefrontDefined(): void
    {
        $kernel = new MockedKernel([
            'ConfigWithoutStorefrontDefined' => new ConfigWithoutStorefrontDefined(),
            'Storefront' => new Storefront(),
            'PayPal' => $this->createMock(Bundle::class),
        ]);

        $builder = new ThemeInheritanceBuilder($kernel);

        $inheritance = $builder->build(
            ['ConfigWithoutStorefrontDefined', 'Storefront', 'PayPal'],
            ['ConfigWithoutStorefrontDefined' => true]
        );

        static::assertEquals(['PayPal', 'ConfigWithoutStorefrontDefined'], $inheritance);
    }

    public function testPluginWildcardAndExplicit(): void
    {
        $kernel = new MockedKernel([
            'PluginWildcardAndExplicit' => new PluginWildcardAndExplicit(),
            'Storefront' => new Storefront(),
            'PayPal' => $this->createMock(Bundle::class),
            'CustomProducts' => $this->createMock(Bundle::class),
        ]);

        $builder = new ThemeInheritanceBuilder($kernel);

        $inheritance = $builder->build(
            ['PluginWildcardAndExplicit', 'Storefront', 'PayPal', 'CustomProducts'],
            ['PluginWildcardAndExplicit' => true, 'Storefront' => true]
        );

        static::assertEquals(['CustomProducts', 'PluginWildcardAndExplicit', 'PayPal', 'Storefront'], $inheritance);
    }

    public function testThemeWithoutStorefront(): void
    {
        $kernel = new MockedKernel([
            'ThemeWithoutStorefront' => new ThemeWithoutStorefront(),
            'Storefront' => new Storefront(),
            'PayPal' => $this->createMock(Bundle::class),
            'CustomProducts' => $this->createMock(Bundle::class),
        ]);

        $builder = new ThemeInheritanceBuilder($kernel);

        $inheritance = $builder->build(
            ['ThemeWithoutStorefront', 'Storefront', 'PayPal', 'CustomProducts'],
            ['ThemeWithoutStorefront' => true, 'Storefront' => true]
        );

        static::assertEquals(['CustomProducts', 'ThemeWithoutStorefront', 'PayPal'], $inheritance);
    }

    public function testMultiInheritance(): void
    {
        $kernel = new MockedKernel([
            'ThemeWithMultiInheritance' => new ThemeWithMultiInheritance(),
            'ThemeA' => $this->createMock(SimpleTheme::class),
            'ThemeB' => $this->createMock(SimpleTheme::class),
            'ThemeC' => $this->createMock(SimpleTheme::class),

            // paypal is a plugin and should be registered
            'PayPal' => $this->createMock(Bundle::class),

            // theme d is not included in theme.json
            'ThemeD' => $this->createMock(SimpleTheme::class),
        ]);

        $builder = new ThemeInheritanceBuilder($kernel);

        $inheritance = $builder->build(
            ['ThemeWithMultiInheritance', 'ThemeA', 'ThemeB', 'ThemeC', 'ThemeD', 'PayPal'],
            ['ThemeWithMultiInheritance' => true]
        );

        static::assertEquals(
            ['ThemeWithMultiInheritance', 'ThemeC', 'PayPal', 'ThemeB', 'ThemeA'],
            $inheritance
        );
    }
}

class MockedKernel extends Kernel
{
    public function __construct(array $bundles)
    {
        $this->bundles = $bundles;
    }
}
