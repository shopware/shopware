<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Theme;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Storefront;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\AbstractStorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\ThemeRuntimeConfig;
use Shopware\Storefront\Theme\ThemeRuntimeConfigService;
use Shopware\Storefront\Theme\Twig\ThemeInheritanceBuilder;
use Shopware\Storefront\Theme\Twig\ThemeInheritanceBuilderInterface;
use Shopware\Tests\Integration\Storefront\Theme\fixtures\ConfigWithoutStorefrontDefined\ConfigWithoutStorefrontDefined;
use Shopware\Tests\Integration\Storefront\Theme\fixtures\InheritanceWithConfig\InheritanceWithConfig;
use Shopware\Tests\Integration\Storefront\Theme\fixtures\PluginWildcardAndExplicit\PluginWildcardAndExplicit;
use Shopware\Tests\Integration\Storefront\Theme\fixtures\SimplePlugin\SimplePlugin;
use Shopware\Tests\Integration\Storefront\Theme\fixtures\SimpleTheme\SimpleTheme;
use Shopware\Tests\Integration\Storefront\Theme\fixtures\ThemeWithMultiInheritance\ThemeWithMultiInheritance;
use Shopware\Tests\Integration\Storefront\Theme\fixtures\ThemeWithoutStorefront\ThemeWithoutStorefront;

/**
 * @internal
 */
class ThemeInheritanceBuilderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private ThemeRuntimeConfigTestService $themeRuntimeConfigService;

    private ThemeInheritanceBuilderInterface $builder;

    private AbstractStorefrontPluginConfigurationFactory $configFactory;

    protected function setUp(): void
    {
        $this->themeRuntimeConfigService = new ThemeRuntimeConfigTestService();

        $this->builder = new ThemeInheritanceBuilder($this->themeRuntimeConfigService);

        $this->configFactory = static::getContainer()->get(StorefrontPluginConfigurationFactory::class);
    }

    public function testInheritanceWithConfig(): void
    {
        $configs = new StorefrontPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Storefront()),
            $this->configFactory->createFromBundle(new InheritanceWithConfig()),
        ]);

        $this->themeRuntimeConfigService->initFromConfigCollection($configs);

        $inheritance = $this->builder->build(
            ['InheritanceWithConfig' => 1, 'Storefront' => 1],
            ['InheritanceWithConfig' => true, 'Storefront' => true]
        );

        static::assertEquals(['InheritanceWithConfig', 'Storefront'], array_keys($inheritance));
    }

    public function testEnsurePlugins(): void
    {
        $configs = new StorefrontPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Storefront()),
            $this->configFactory->createFromBundle(new InheritanceWithConfig()),
            $this->configFactory->createFromBundle($this->getMockedPlugin('PayPal', SimplePlugin::class)),
        ]);

        $this->themeRuntimeConfigService->initFromConfigCollection($configs);

        $inheritance = $this->builder->build(
            ['InheritanceWithConfig' => 1, 'Storefront' => 1, 'PayPal' => 1],
            ['InheritanceWithConfig' => true, 'Storefront' => true]
        );

        static::assertEquals(['PayPal', 'InheritanceWithConfig', 'Storefront'], array_keys($inheritance));
    }

    public function testConfigWithoutStorefrontDefined(): void
    {
        $configs = new StorefrontPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Storefront()),
            $this->configFactory->createFromBundle(new ConfigWithoutStorefrontDefined()),
            $this->configFactory->createFromBundle($this->getMockedPlugin('PayPal', SimplePlugin::class)),
        ]);

        $this->themeRuntimeConfigService->initFromConfigCollection($configs);

        $inheritance = $this->builder->build(
            ['ConfigWithoutStorefrontDefined' => 1, 'Storefront' => 1, 'PayPal' => 1],
            ['ConfigWithoutStorefrontDefined' => true]
        );

        static::assertEquals(['PayPal', 'ConfigWithoutStorefrontDefined'], array_keys($inheritance));
    }

    public function testPluginWildcardAndExplicit(): void
    {
        $configs = new StorefrontPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Storefront()),
            $this->configFactory->createFromBundle(new PluginWildcardAndExplicit()),
            $this->configFactory->createFromBundle($this->getMockedPlugin('PayPal', SimplePlugin::class)),
            $this->configFactory->createFromBundle($this->getMockedPlugin('CustomProducts', SimplePlugin::class)),
        ]);

        $this->themeRuntimeConfigService->initFromConfigCollection($configs);

        $inheritance = $this->builder->build(
            ['PluginWildcardAndExplicit' => 1, 'Storefront' => 1, 'PayPal' => 1, 'CustomProducts' => 1],
            ['PluginWildcardAndExplicit' => true, 'Storefront' => true]
        );

        static::assertEquals(['CustomProducts', 'PluginWildcardAndExplicit', 'PayPal', 'Storefront'], array_keys($inheritance));
    }

    public function testThemeWithoutStorefront(): void
    {
        $configs = new StorefrontPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Storefront()),
            $this->configFactory->createFromBundle(new ThemeWithoutStorefront()),
            $this->configFactory->createFromBundle($this->getMockedPlugin('PayPal', SimplePlugin::class)),
            $this->configFactory->createFromBundle($this->getMockedPlugin('CustomProducts', SimplePlugin::class)),
        ]);

        $this->themeRuntimeConfigService->initFromConfigCollection($configs);

        $inheritance = $this->builder->build(
            ['ThemeWithoutStorefront' => 1, 'Storefront' => 1, 'PayPal' => 1, 'CustomProducts' => 1],
            ['ThemeWithoutStorefront' => true, 'Storefront' => true]
        );

        static::assertEquals(['CustomProducts', 'ThemeWithoutStorefront', 'PayPal'], array_keys($inheritance));
    }

    public function testMultiInheritance(): void
    {
        $configs = new StorefrontPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Storefront()),
            $this->configFactory->createFromBundle(new ThemeWithMultiInheritance(true, __DIR__ . '/fixtures/SimplePlugin')),
            $this->configFactory->createFromBundle($this->getMockedPlugin('ThemeA', SimpleTheme::class)),
            $this->configFactory->createFromBundle($this->getMockedPlugin('ThemeB', SimpleTheme::class)),
            $this->configFactory->createFromBundle($this->getMockedPlugin('ThemeC', SimpleTheme::class)),

            // paypal is a plugin and should be registered
            $this->configFactory->createFromBundle($this->getMockedPlugin('PayPal', SimplePlugin::class)),

            // theme d is not included in theme.json
            $this->configFactory->createFromBundle($this->getMockedPlugin('ThemeD', SimpleTheme::class)),
        ]);

        $this->themeRuntimeConfigService->initFromConfigCollection($configs);

        $inheritance = $this->builder->build(
            ['ThemeWithMultiInheritance' => 1, 'ThemeA' => 1, 'ThemeB' => 1, 'ThemeC' => 1, 'ThemeD' => 1, 'PayPal' => 1],
            ['ThemeWithMultiInheritance' => true]
        );

        static::assertEquals(
            ['ThemeWithMultiInheritance', 'ThemeC', 'PayPal', 'ThemeB', 'ThemeA'],
            array_keys($inheritance)
        );
    }

    /**
     * @param class-string $pluginClass
     */
    private function getMockedPlugin(string $pluginName, string $pluginClass): Bundle
    {
        /** @var Bundle $bundle */
        $bundle = new $pluginClass(true, __DIR__ . '/fixtures/SimplePlugin');

        $reflection = new \ReflectionClass($pluginClass);
        $name = $reflection->getProperty('name');
        $name->setAccessible(true);
        $name->setValue($bundle, $pluginName);

        return $bundle;
    }
}

/**
 * @internal
 */
class ThemeRuntimeConfigTestService extends ThemeRuntimeConfigService
{
    /**
     * @var array<string, ThemeRuntimeConfig>
     */
    private array $configs = [];

    public function __construct(
    ) {
    }

    public function initFromConfigCollection(StorefrontPluginConfigurationCollection $configurationCollection): void
    {
        $this->configs = [];
        foreach ($configurationCollection as $plugin) {
            if (!$plugin->getIsTheme()) {
                continue;
            }

            $this->configs[$plugin->getTechnicalName()] = ThemeRuntimeConfig::fromArray([
                'themeId' => Uuid::randomHex(),
                'technicalName' => $plugin->getTechnicalName(),
                'viewInheritance' => $plugin->getViewInheritance(),
            ]);
        }
    }

    public function getActiveThemeNames(): array
    {
        return array_keys($this->configs);
    }

    public function getRuntimeConfigByName(string $technicalName): ?ThemeRuntimeConfig
    {
        return $this->configs[$technicalName] ?? null;
    }
}
