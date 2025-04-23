<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Storefront\Theme\Exception\ThemeException;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\ThemeCollection;
use Shopware\Storefront\Theme\ThemeEntity;
use Shopware\Storefront\Theme\ThemeMergedConfigBuilder;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeFixtures;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeMergedConfigBuilderFixtures;

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

    public function testGetThemeConfigurationNoTheme(): void
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

        $this->expectExceptionObject(ThemeException::couldNotFindThemeById($themeId));

        $this->mergedConfigBuilder->getThemeConfiguration($themeId, false, $this->context);
    }

    /**
     * @param array<string, mixed> $ids
     * @param array<string, mixed>|null $expected
     * @param array<string, mixed>|null $expectedNotTranslated
     * @param array<string, mixed>|null $expectedStructured
     * @param array<string, mixed>|null $expectedStructuredNotTranslated
     */
    #[DataProvider('getThemeCollectionForThemeConfiguration')]
    public function testGetThemeConfiguration(
        array $ids,
        ThemeCollection $themeCollection,
        ?array $expected = null,
        ?array $expectedNotTranslated = null,
        ?array $expectedStructured = null,
        ?array $expectedStructuredNotTranslated = null
    ): void {
        $this->themeRepositoryMock->method('search')->willReturn(
            new EntitySearchResult(
                'theme',
                1,
                $themeCollection,
                null,
                new Criteria(),
                $this->context
            )
        );

        $storefrontPlugin = new StorefrontPluginConfiguration('Test');
        $storefrontPlugin->setThemeConfig(ThemeFixtures::getThemeJsonConfig());

        $this->storefrontPluginRegistryMock->method('getConfigurations')->willReturn(
            new StorefrontPluginConfigurationCollection(
                [
                    $storefrontPlugin,
                ]
            )
        );

        $config = $this->mergedConfigBuilder->getThemeConfiguration($ids['themeId'], true, $this->context);

        static::assertArrayHasKey('fields', $config);
        static::assertArrayHasKey('currentFields', $config);
        static::assertArrayHasKey('baseThemeFields', $config);
        static::assertEquals($expected, $config);
    }

    /**
     * @param array<string, mixed> $ids
     * @param array<string, mixed>|null $expected
     * @param array<string, mixed>|null $expectedNotTranslated
     * @param array<string, mixed>|null $expectedStructured
     * @param array<string, mixed>|null $expectedStructuredNotTranslated
     */
    #[DataProvider('getThemeCollectionForThemeConfiguration')]
    public function testGetThemeConfigurationNoTranslation(
        array $ids,
        ThemeCollection $themeCollection,
        ?array $expected = null,
        ?array $expectedNotTranslated = null,
        ?array $expectedStructured = null,
        ?array $expectedStructuredNotTranslated = null
    ): void {
        if ($expectedNotTranslated !== null) {
            $expected = $expectedNotTranslated;
        }

        $this->themeRepositoryMock->method('search')->willReturn(
            new EntitySearchResult(
                'theme',
                1,
                $themeCollection,
                null,
                new Criteria(),
                $this->context
            )
        );

        $storefrontPlugin = new StorefrontPluginConfiguration('Test');
        $storefrontPlugin->setThemeConfig(ThemeFixtures::getThemeJsonConfig());

        $this->storefrontPluginRegistryMock->method('getConfigurations')->willReturn(
            new StorefrontPluginConfigurationCollection(
                [
                    $storefrontPlugin,
                ]
            )
        );

        $config = $this->mergedConfigBuilder->getThemeConfiguration($ids['themeId'], false, $this->context);

        static::assertArrayHasKey('fields', $config);
        static::assertArrayHasKey('currentFields', $config);
        static::assertArrayHasKey('baseThemeFields', $config);
        static::assertEquals($expected, $config);
    }

    /**
     * @param array<string, mixed> $ids
     * @param array<string, mixed>|null $expected
     * @param array<string, mixed>|null $expectedNotTranslated
     * @param array<string, mixed>|null $expectedStructured
     * @param array<string, mixed>|null $expectedStructuredNotTranslated
     */
    #[DataProvider('getThemeCollectionForThemeConfiguration')]
    public function testGetThemeConfigurationStructured(
        array $ids,
        ThemeCollection $themeCollection,
        ?array $expected = null,
        ?array $expectedNotTranslated = null,
        ?array $expectedStructured = null,
        ?array $expectedStructuredNotTranslated = null
    ): void {
        $this->themeRepositoryMock->method('search')->willReturn(
            new EntitySearchResult(
                'theme',
                1,
                $themeCollection,
                null,
                new Criteria(),
                $this->context
            )
        );

        $storefrontPlugin = new StorefrontPluginConfiguration('Test');
        $storefrontPlugin->setThemeConfig(ThemeFixtures::getThemeJsonConfig());

        $this->storefrontPluginRegistryMock->method('getConfigurations')->willReturn(
            new StorefrontPluginConfigurationCollection(
                [
                    $storefrontPlugin,
                ]
            )
        );

        $config = $this->mergedConfigBuilder->getThemeConfigurationStructuredFields($ids['themeId'], true, $this->context);

        static::assertArrayHasKey('tabs', $config);
        static::assertArrayHasKey('default', $config['tabs']);
        static::assertArrayHasKey('blocks', $config['tabs']['default']);
        static::assertEquals($expectedStructured, $config);
    }

    /**
     * @param array<string, mixed> $ids
     * @param array<string, mixed>|null $expected
     * @param array<string, mixed>|null $expectedNotTranslated
     * @param array<string, mixed>|null $expectedStructured
     * @param array<string, mixed>|null $expectedStructuredNotTranslated
     */
    #[DataProvider('getThemeCollectionForThemeConfiguration')]
    public function testGetThemeConfigurationStructuredNoTranslation(
        array $ids,
        ThemeCollection $themeCollection,
        ?array $expected = null,
        ?array $expectedNotTranslated = null,
        ?array $expectedStructured = null,
        ?array $expectedStructuredNotTranslated = null
    ): void {
        if ($expectedStructuredNotTranslated !== null) {
            $expectedStructured = $expectedStructuredNotTranslated;
        }

        $this->themeRepositoryMock->method('search')->willReturn(
            new EntitySearchResult(
                'theme',
                1,
                $themeCollection,
                null,
                new Criteria(),
                $this->context
            )
        );

        $storefrontPlugin = new StorefrontPluginConfiguration('Test');
        $storefrontPlugin->setThemeConfig(ThemeFixtures::getThemeJsonConfig());

        $this->storefrontPluginRegistryMock->method('getConfigurations')->willReturn(
            new StorefrontPluginConfigurationCollection(
                [
                    $storefrontPlugin,
                ]
            )
        );

        $config = $this->mergedConfigBuilder->getThemeConfigurationStructuredFields($ids['themeId'], false, $this->context);

        static::assertArrayHasKey('tabs', $config);
        static::assertArrayHasKey('default', $config['tabs']);
        static::assertArrayHasKey('blocks', $config['tabs']['default']);
        static::assertEquals($expectedStructured, $config);
    }

    /**
     * @return iterable<array{
     *     ids: array<string, mixed>,
     *     themeCollection: ThemeCollection,
     *     expected?: array<string, mixed>,
     *     expectedNotTranslated?: array<string, mixed>|null,
     *     expectedStructured?: array<string, mixed>,
     *     expectedStructuredNotTranslated?: array<string, mixed>
     * }>
     */
    public static function getThemeCollectionForThemeConfiguration(): iterable
    {
        foreach (ThemeMergedConfigBuilderFixtures::getTestCases() as $testCase) {
            yield $testCase;
        }
    }
}
