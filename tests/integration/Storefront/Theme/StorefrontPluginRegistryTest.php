<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Theme;

use Shopware\Core\Test\AppSystemTestBehaviour;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;

/**
 * @internal
 */
class StorefrontPluginRegistryTest extends \Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestCase
{
    use AppSystemTestBehaviour;

    public function testConfigIsAddedIfItsATheme(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/fixtures/Apps/theme');

        $registry = static::getContainer()
            ->get(StorefrontPluginRegistry::class);

        static::assertInstanceOf(
            StorefrontPluginConfiguration::class,
            $registry->getConfigurations()->getByTechnicalName('SwagTheme')
        );
    }

    public function testConfigIsNotAddedIfAppIsNotActive(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/fixtures/Apps/theme', false);

        $registry = static::getContainer()
            ->get(StorefrontPluginRegistry::class);

        static::assertNull(
            $registry->getConfigurations()->getByTechnicalName('SwagTheme')
        );
    }

    public function testConfigIsAddedIfHasResourcesToCompile(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/fixtures/Apps/noThemeCustomCss');

        $registry = static::getContainer()
            ->get(StorefrontPluginRegistry::class);

        static::assertInstanceOf(
            StorefrontPluginConfiguration::class,
            $registry->getConfigurations()->getByTechnicalName('SwagNoThemeCustomCss')
        );
    }

    public function testConfigIsNotAddedButIdentifiedAsNotThemeIfItsNotATheme(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/fixtures/Apps/noThemeNoCss');

        $registry = static::getContainer()
            ->get(StorefrontPluginRegistry::class);

        static::assertInstanceOf(
            StorefrontPluginConfiguration::class,
            $registry->getConfigurations()->getByTechnicalName('SwagNoThemeNoCss')
        );

        static::assertNull(
            $registry->getConfigurations()->getThemes()->getByTechnicalName('SwagNoThemeNoCss')
        );
    }
}
