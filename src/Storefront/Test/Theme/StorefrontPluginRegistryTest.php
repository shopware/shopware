<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Theme;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\App\AppSystemTestBehaviour;
use Shopware\Core\Framework\Test\App\StorefrontPluginRegistryTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;

class StorefrontPluginRegistryTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AppSystemTestBehaviour;
    use StorefrontPluginRegistryTestBehaviour;

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

    public function testConfigIsNotAddedIfItsNotATheme(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/fixtures/Apps/noThemeNoCss');

        $registry = $this->getContainer()
            ->get(StorefrontPluginRegistry::class);

        static::assertNull(
            $registry->getConfigurations()->getByTechnicalName('SwagNoThemeNoCss')
        );
    }
}
