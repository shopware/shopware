<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Theme;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Test\AppSystemTestBehaviour;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;

/**
 * @internal
 */
class StorefrontPluginRegistryTest extends TestCase
{
    use AppSystemTestBehaviour;
    use IntegrationTestBehaviour;

    public function testConfigIsAddedIfItsATheme(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/fixtures/Apps/theme');

        $registry = $this->getContainer()
            ->get(StorefrontPluginRegistry::class);

        static::assertInstanceOf(
            StorefrontPluginConfiguration::class,
            $registry->getConfigurations()->getByTechnicalName('SwagTheme')
        );
    }

    public function testConfigIsNotAddedIfAppIsNotActive(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/fixtures/Apps/theme', false);

        $registry = $this->getContainer()
            ->get(StorefrontPluginRegistry::class);

        static::assertNull(
            $registry->getConfigurations()->getByTechnicalName('SwagTheme')
        );
    }

    public function testConfigIsAddedIfHasResourcesToCompile(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/fixtures/Apps/noThemeCustomCss');

        $registry = $this->getContainer()
            ->get(StorefrontPluginRegistry::class);

        static::assertInstanceOf(
            StorefrontPluginConfiguration::class,
            $registry->getConfigurations()->getByTechnicalName('SwagNoThemeCustomCss')
        );
    }

    public function testConfigIsNotAddedButIdentifiedAsNotThemeIfItsNotATheme(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/fixtures/Apps/noThemeNoCss');

        $registry = $this->getContainer()
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
