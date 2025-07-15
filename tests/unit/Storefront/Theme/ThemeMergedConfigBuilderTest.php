<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Storefront\Theme\Exception\ThemeException;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\ThemeCollection;
use Shopware\Storefront\Theme\ThemeEntity;
use Shopware\Storefront\Theme\ThemeMergedConfigBuilder;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeFixtures;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeFixtures_6_7;

/**
 * @internal
 */
#[CoversClass(ThemeMergedConfigBuilder::class)]
class ThemeMergedConfigBuilderTest extends TestCase
{
    private StorefrontPluginRegistry&MockObject $storefrontPluginRegistryMock;

    /** @var EntityRepository<ThemeCollection>&MockObject */
    private EntityRepository&MockObject $themeRepositoryMock;

    private ThemeMergedConfigBuilder $mergedConfigBuilder;

    private Context $context;

    protected function setUp(): void
    {
        $this->storefrontPluginRegistryMock = $this->createMock(StorefrontPluginRegistry::class);
        $this->themeRepositoryMock = $this->createMock(EntityRepository::class);

        $this->context = Context::createDefaultContext();

        $this->mergedConfigBuilder = new ThemeMergedConfigBuilder(
            $this->storefrontPluginRegistryMock,
            $this->themeRepositoryMock,
        );
    }

    public function testGetPlainThemeConfigurationNoTheme(): void
    {
        $themeId = Uuid::randomHex();

        $this->themeRepositoryMock->method('search')->willReturn(
            new EntitySearchResult(
                'theme',
                1,
                new ThemeCollection(
                    [
                        (new ThemeEntity())->assign(
                            [
                                '_uniqueIdentifier' => 'no',
                                'salesChannels' => new SalesChannelCollection(),
                            ]
                        ),
                    ]
                ),
                null,
                new Criteria(),
                $this->context
            )
        );

        $this->expectException(ThemeException::class);
        $this->expectExceptionMessage(\sprintf('Could not find theme with id "%s"', $themeId));

        $this->mergedConfigBuilder->getPlainThemeConfiguration($themeId, $this->context);
    }

    /**
     * @deprecated tag:v6.8.0 Will be removed, use testGetPlainThemeConfiguration instead
     *
     * @param array<string, mixed> $ids
     * @param array<string, mixed>|null $expected
     * @param array<string, mixed>|null $expectedStructured
     */
    #[DataProviderExternal(ThemeFixtures_6_7::class, 'getThemeCollectionForThemeConfiguration')]
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetPlainThemeConfigurationWithTranslations(
        array $ids,
        ThemeCollection $themeCollection,
        ?array $expected = null,
        ?array $expectedStructured = null,
    ): void {
        $this->testGetPlainThemeConfiguration($ids, $themeCollection, $expected, $expectedStructured);
    }

    /**
     * @param array<string, mixed> $ids
     * @param array<string, mixed>|null $expected
     * @param array<string, mixed>|null $expectedStructured
     */
    #[DataProviderExternal(ThemeFixtures::class, 'getThemeCollectionForThemeConfiguration')]
    public function testGetPlainThemeConfiguration(
        array $ids,
        ThemeCollection $themeCollection,
        ?array $expected = null,
        ?array $expectedStructured = null,
    ): void {
        $this->mockThemeRepositorySearch($themeCollection);

        $storefrontPlugin = new StorefrontPluginConfiguration('Test');
        $storefrontPlugin->setThemeConfig(ThemeFixtures::getThemeJsonConfig());

        $this->storefrontPluginRegistryMock->method('getConfigurations')->willReturn(
            new StorefrontPluginConfigurationCollection(
                [
                    $storefrontPlugin,
                ]
            )
        );

        $config = $this->mergedConfigBuilder->getPlainThemeConfiguration($ids['themeId'], $this->context, true);

        static::assertArrayHasKey('fields', $config);
        static::assertArrayHasKey('currentFields', $config);
        static::assertArrayHasKey('baseThemeFields', $config);
        static::assertEquals($expected, $config);
    }

    /**
     * @deprecated tag:v6.8.0 Will be removed, use testGetThemeConfigurationFieldStructure instead
     *
     * @param array<string, mixed> $ids
     * @param array<string, mixed>|null $expected
     * @param array<string, mixed>|null $expectedStructured
     */
    #[DataProviderExternal(ThemeFixtures_6_7::class, 'getThemeCollectionForThemeConfiguration')]
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetThemeConfigurationFieldStructureWithTranslations(
        array $ids,
        ThemeCollection $themeCollection,
        ?array $expected = null,
        ?array $expectedStructured = null,
    ): void {
        $this->testGetThemeConfigurationFieldStructure($ids, $themeCollection, $expected, $expectedStructured);
    }

    /**
     * @param array<string, mixed> $ids
     * @param array<string, mixed>|null $expected
     * @param array<string, mixed>|null $expectedStructured
     */
    #[DataProviderExternal(ThemeFixtures::class, 'getThemeCollectionForThemeConfiguration')]
    public function testGetThemeConfigurationFieldStructure(
        array $ids,
        ThemeCollection $themeCollection,
        ?array $expected = null,
        ?array $expectedStructured = null,
    ): void {
        $this->mockThemeRepositorySearch($themeCollection);

        $storefrontPlugin = new StorefrontPluginConfiguration('Test');
        $storefrontPlugin->setThemeConfig(ThemeFixtures::getThemeJsonConfig());

        $this->storefrontPluginRegistryMock->method('getConfigurations')->willReturn(
            new StorefrontPluginConfigurationCollection(
                [
                    $storefrontPlugin,
                ]
            )
        );

        $config = $this->mergedConfigBuilder->getThemeConfigurationFieldStructure($ids['themeId'], $this->context, true);

        static::assertArrayHasKey('tabs', $config);
        static::assertArrayHasKey('default', $config['tabs']);
        static::assertArrayHasKey('blocks', $config['tabs']['default']);
        static::assertEquals($expectedStructured, $config);
    }

    private function mockThemeRepositorySearch(ThemeCollection $themeCollection): void
    {
        // Set up the mock to handle both the main search and the parent theme search
        $this->themeRepositoryMock->method('search')->willReturnCallback(
            function (Criteria $criteria) use ($themeCollection) {
                // If the criteria has a filter for a specific ID, find that theme
                $filters = $criteria->getFilters();
                foreach ($filters as $filter) {
                    if ($filter instanceof \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter
                        && $filter->getField() === 'id') {
                        $searchId = (string) $filter->getValue();
                        $foundTheme = $themeCollection->get($searchId);

                        if ($foundTheme) {
                            return new EntitySearchResult(
                                'theme',
                                1,
                                new ThemeCollection([$foundTheme]),
                                null,
                                $criteria,
                                $this->context
                            );
                        }
                    }
                }

                // Default: return the full collection for the main search
                return new EntitySearchResult(
                    'theme',
                    $themeCollection->count(),
                    $themeCollection,
                    null,
                    $criteria,
                    $this->context
                );
            }
        );
    }
}
