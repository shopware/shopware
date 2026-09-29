<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\StorefrontPluginConfiguration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(StorefrontPluginConfigurationCollection::class)]
class StorefrontPluginConfigurationCollectionTest extends TestCase
{
    public function testElementsAreKeyedByTechnicalName(): void
    {
        $theme = self::configuration('SwagTheme', isTheme: true);

        $collection = new StorefrontPluginConfigurationCollection([$theme]);

        static::assertSame($theme, $collection->get('SwagTheme'));
    }

    public function testAddKeysTheElementByTechnicalName(): void
    {
        $plugin = self::configuration('SwagPlugin', isTheme: false);

        $collection = new StorefrontPluginConfigurationCollection();
        $collection->add($plugin);

        static::assertSame($plugin, $collection->get('SwagPlugin'));
    }

    public function testGetByTechnicalName(): void
    {
        $theme = self::configuration('SwagTheme', isTheme: true);
        $collection = new StorefrontPluginConfigurationCollection([$theme]);

        static::assertSame($theme, $collection->getByTechnicalName('SwagTheme'));
        static::assertNull($collection->getByTechnicalName('Unknown'));
    }

    public function testGetThemesReturnsOnlyThemes(): void
    {
        $theme = self::configuration('SwagTheme', isTheme: true);
        $plugin = self::configuration('SwagPlugin', isTheme: false);

        $collection = new StorefrontPluginConfigurationCollection([$theme, $plugin]);

        static::assertSame([$theme], array_values($collection->getThemes()->getElements()));
        static::assertSame([$plugin], array_values($collection->getNoneThemes()->getElements()));
    }

    private static function configuration(string $technicalName, bool $isTheme): StorefrontPluginConfiguration
    {
        $configuration = new StorefrontPluginConfiguration($technicalName);
        $configuration->setIsTheme($isTheme);

        return $configuration;
    }
}
