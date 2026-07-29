<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\ConfigLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Storefront\Theme\ConfigLoader\DatabaseConfigLoader;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\ThemeCollection;
use Shopware\Storefront\Theme\ThemeDefinition;
use Shopware\Storefront\Theme\ThemeEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DatabaseConfigLoader::class)]
class DatabaseConfigLoaderTest extends TestCase
{
    /**
     * @var StaticEntityRepository<ThemeCollection>
     */
    private StaticEntityRepository $themeRepository;

    private MockObject&StorefrontPluginRegistry $storefrontPluginRegistry;

    /**
     * @var StaticEntityRepository<MediaCollection>
     */
    private StaticEntityRepository $mediaRepository;

    private DatabaseConfigLoader $databaseConfigLoader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->themeRepository = new StaticEntityRepository([], new ThemeDefinition());
        $this->storefrontPluginRegistry = $this->createMock(StorefrontPluginRegistry::class);
        $this->mediaRepository = new StaticEntityRepository([new MediaCollection([])], new MediaDefinition());

        $this->databaseConfigLoader = new DatabaseConfigLoader(
            $this->themeRepository,
            $this->storefrontPluginRegistry,
            $this->mediaRepository
        );
    }

    public function testItLoadsStorefrontPluginConfiguration(): void
    {
        $themeId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $theme = new ThemeEntity();
        $theme->setId($themeId);
        $theme->setName('FooBar');
        $theme->setTechnicalName('FooBar');
        $theme->setActive(true);
        $theme->setBaseConfig([
            'foo' => [
                'type' => 'media',
                'value' => 'bar',
            ],
            'bar' => [
                'type' => 'media',
                'value' => 'foo',
            ],
        ]);
        $theme->setConfigValues([
            'foo' => [
                'type' => 'media',
                'value' => null,
            ],
        ]);

        $baseTheme = new ThemeEntity();
        $baseTheme->setId(Uuid::randomHex());
        $baseTheme->setTechnicalName(StorefrontPluginRegistry::BASE_THEME_NAME);

        $this->themeRepository->searches = [new ThemeCollection([$theme]), new ThemeCollection([$theme, $baseTheme])];

        $configuration = new StorefrontPluginConfiguration('FooBar');
        $configuration->setThemeConfig($theme->getBaseConfig());

        $this->storefrontPluginRegistry
            ->expects($this->exactly(3))
            ->method('getConfigurations')
            ->willReturn(new StorefrontPluginConfigurationCollection([$configuration]));

        $config = $this->databaseConfigLoader->load($themeId, $context);

        static::assertNotNull($config->getThemeConfig());
        static::assertArrayHasKey('foo', $config->getThemeConfig());
        static::assertNull($config->getThemeConfig()['foo']['value']);
        static::assertArrayHasKey('bar', $config->getThemeConfig());
        static::assertSame('foo', $config->getThemeConfig()['bar']['value']);
    }
}
