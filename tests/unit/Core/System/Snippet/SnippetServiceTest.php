<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Snippet\Files\SnippetFileCollection;
use Shopware\Core\System\Snippet\Filter\SnippetFilterFactory;
use Shopware\Core\System\Snippet\SnippetException;
use Shopware\Core\System\Snippet\SnippetService;
use Shopware\Storefront\Theme\DatabaseSalesChannelThemeLoader;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Tests\Unit\Core\System\Snippet\Mock\MockSnippetFile;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Translation\MessageCatalogue;

/**
 * @internal
 */
#[CoversClass(SnippetService::class)]
class SnippetServiceTest extends TestCase
{
    private SnippetFileCollection $snippetCollection;

    private Connection&MockObject $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->snippetCollection = new SnippetFileCollection();
        $this->addThemes();
    }

    /**
     * @param list<string> $catalogMessages
     * @param \Throwable|list<string> $expected
     * @param list<string> $databaseSnippets
     */
    #[DataProvider('getStorefrontSnippetsDataProvider')]
    public function testGetStorefrontSnippets(
        array|\Throwable $expected = [],
        false|string $fetchLocaleResult = 'en-GB',
        array $catalogMessages = [],
        ?string $fallbackLocale = null,
        ?string $salesChannelId = null,
        bool $withThemeRegistry = true,
        ?string $usedTheme = null,
        array $databaseSnippets = []
    ): void {
        $classExists = class_exists(StorefrontPluginRegistry::class);

        if ($withThemeRegistry && !$classExists) {
            $this->testGetStorefrontSnippetsWithoutThemeRegistry();

            return;
        }

        if ($expected instanceof \Throwable) {
            $this->expectException($expected::class);
        }

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->with(StorefrontPluginRegistry::class)->willReturn($withThemeRegistry);
        $this->connection->expects(static::once())->method('fetchOne')->willReturn($fetchLocaleResult);

        if ($withThemeRegistry) {
            $plugins = new StorefrontPluginConfigurationCollection();

            foreach (['Storefront', 'SwagTheme'] as $technicalName) {
                $theme = new StorefrontPluginConfiguration($technicalName);
                $theme->setIsTheme(true);
                $plugins->add($theme);
            }

            $themeRegistry = $this->createMock(StorefrontPluginRegistry::class);
            $themeRegistry->expects(static::once())->method('getConfigurations')->willReturn($plugins);
            $container->expects(static::once())->method('get')->with(StorefrontPluginRegistry::class)->willReturn($themeRegistry);
        }

        $cachedThemeLoader = null;
        if ($salesChannelId !== null) {
            $expectedDB = [
                'themeName' => $usedTheme ?? 'Storefront',
                'parentThemeName' => null,
                'themeId' => Uuid::randomHex(),
            ];
            $connectionMock = $this->createMock(Connection::class);
            $connectionMock->expects(static::once())->method('fetchAssociative')->willReturn($expectedDB);
            $cachedThemeLoader = new DatabaseSalesChannelThemeLoader($connectionMock);
        }

        if ($databaseSnippets !== []) {
            $this->connection->expects(static::once())->method('fetchAllKeyValue')->willReturn($databaseSnippets);
        }

        $snippetService = new SnippetService(
            $this->connection,
            $this->snippetCollection,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(SnippetFilterFactory::class),
            $container,
            $cachedThemeLoader,
        );

        $catalog = new MessageCatalogue((string) $fetchLocaleResult, ['messages' => $catalogMessages]);

        $snippets = $snippetService->getStorefrontSnippets($catalog, Uuid::randomHex(), $fallbackLocale, $salesChannelId);

        static::assertEquals($expected, $snippets);
    }

    public function testGetStorefrontSnippetsWithoutThemeRegistry(): void
    {
        $locale = 'de-DE';
        $snippetSetId = Uuid::randomHex();
        $catalog = new MessageCatalogue($locale, []);

        $container = $this->createMock(ContainerInterface::class);
        $container->expects(static::exactly(2))->method('has')->with(StorefrontPluginRegistry::class)->willReturn(false);

        $snippetService = new SnippetService(
            $this->connection,
            $this->snippetCollection,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(SnippetFilterFactory::class),
            $container
        );

        $snippets = $snippetService->getStorefrontSnippets($catalog, $snippetSetId, $locale);

        static::assertSame([
            'title' => 'SwagTheme DE',
        ], $snippets);
    }

    public function testFindSnippetSetIdWithSalesChannelDomain(): void
    {
        $snippetSetIdWithSalesChannelDomain = Uuid::randomHex();

        $this->connection->expects(static::once())->method('fetchOne')->willReturn($snippetSetIdWithSalesChannelDomain);

        $snippetService = new SnippetService(
            $this->connection,
            $this->snippetCollection,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(SnippetFilterFactory::class),
            $this->createMock(ContainerInterface::class),
        );

        $snippetSetId = $snippetService->findSnippetSetId(Uuid::randomHex(), Uuid::randomHex(), 'en-GB');

        static::assertSame($snippetSetId, $snippetSetIdWithSalesChannelDomain);
    }

    /**
     * @param array<string, string> $locales
     */
    #[DataProvider('findSnippetSetIdDataProvider')]
    public function testFindSnippetSetIdWithoutSalesChannelDomain(array $locales, string $id): void
    {
        $this->connection->expects(static::once())->method('fetchOne')->willReturn(null);
        $this->connection->expects(static::once())->method('fetchAllKeyValue')->willReturn($locales);

        $snippetService = new SnippetService(
            $this->connection,
            $this->snippetCollection,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(SnippetFilterFactory::class),
            $this->createMock(ContainerInterface::class),
        );

        $snippetSetId = $snippetService->findSnippetSetId(Uuid::randomHex(), Uuid::randomHex(), 'vi-VN');

        static::assertSame($snippetSetId, $id);
    }

    /**
     * @return iterable<string, array<string|array<string>>>
     */
    public static function findSnippetSetIdDataProvider(): iterable
    {
        $snippetSetIdWithVI = Uuid::randomHex();
        $snippetSetIdWithEN = Uuid::randomHex();

        yield 'get snippet set with local vi-VN' => [
            'sets' => [
                'vi-VN' => $snippetSetIdWithVI,
                'en-GB' => $snippetSetIdWithEN,
            ],
            'snippetSetId' => $snippetSetIdWithVI,
        ];

        yield 'get snippet set without local vi-VN' => [
            'sets' => [
                'en-GB' => $snippetSetIdWithEN,
            ],
            'snippetSetId' => $snippetSetIdWithEN,
        ];
    }

    public static function getStorefrontSnippetsDataProvider(): \Generator
    {
        yield 'with unknown snippet id' => [
            'expected' => SnippetException::snippetSetNotFound('test'),
            'fetchLocaleResult' => false,
            'catalogMessages' => [],
            'fallbackLocale' => null,
            'salesChannelId' => null,
            'withThemeRegistry' => false,
        ];

        yield 'with messages from catalog' => [
            'expected' => [
                'catalog_key' => 'Catalog DE',
                'title' => 'Storefront EN',
            ],
            'fetchLocaleResult' => 'en-GB',
            'catalogMessages' => [
                'catalog_key' => 'Catalog DE',
            ],
        ];

        yield 'fallback snippets are used if no localized snippet found' => [
            'expected' => [
                'title' => 'Storefront DE',
            ],
            'fetchLocaleResult' => 'vi-VN',
            'catalogMessages' => [],
            'fallbackLocale' => 'de-DE',
        ];

        yield 'fallback snippets are overridden by catalog messages' => [
            'expected' => [
                'catalog_key' => 'Catalog DE',
                'title' => 'Catalog title',
            ],
            'fetchLocaleResult' => 'vi-VN',
            'catalogMessages' => [
                'catalog_key' => 'Catalog DE',
                'title' => 'Catalog title',
            ],
            'fallbackLocale' => 'en-GB',
        ];

        yield 'fallback snippets, catalog messages are overridden by localized snippets' => [
            'expected' => [
                'catalog_key' => 'Catalog DE',
                'title' => 'Storefront DE',
            ],
            'fetchLocaleResult' => 'de-DE',
            'catalogMessages' => [
                'catalog_key' => 'Catalog DE',
                'title' => 'Catalog title',
            ],
            'fallbackLocale' => 'en-GB',
        ];

        yield 'fallback snippets, catalog message, localized snippets are overridden by database snippets' => [
            'expected' => [
                'title' => 'Database title',
                'catalog_key' => 'Catalog DE',
            ],
            'fetchLocaleResult' => 'de-DE',
            'catalogMessages' => [
                'catalog_key' => 'Catalog DE',
                'title' => 'Catalog title',
            ],
            'fallbackLocale' => 'en-GB',
            'salesChannelId' => null,
            'withThemeRegistry' => true,
            'usedTheme' => null,
            'databaseSnippets' => [
                'title' => 'Database title',
            ],
        ];

        yield 'with sales channel id without theme' => [
            'expected' => [
                'title' => 'Storefront DE',
            ],
            'fetchLocaleResult' => 'de-DE',
            'catalogMessages' => [],
            'fallbackLocale' => 'en-GB',
            'salesChannelId' => Uuid::randomHex(),
            'withThemeRegistry' => true,
            'usedTheme' => null,
            'databaseSnippets' => [],
        ];

        yield 'with sales channel id and theme' => [
            'expected' => [
                'title' => 'SwagTheme DE',
            ],
            'fetchLocaleResult' => 'de-DE',
            'catalogMessages' => [],
            'fallbackLocale' => 'en-GB',
            'salesChannelId' => Uuid::randomHex(),
            'withThemeRegistry' => true,
            'usedTheme' => 'SwagTheme',
        ];

        yield 'theme snippets are overridden by database snippets' => [
            'expected' => [
                'title' => 'Database title',
                'catalog_key' => 'Catalog DE',
            ],
            'fetchLocaleResult' => 'de-DE',
            'catalogMessages' => [
                'catalog_key' => 'Catalog DE',
                'title' => 'Catalog title',
            ],
            'fallbackLocale' => 'en-GB',
            'salesChannelId' => Uuid::randomHex(),
            'withThemeRegistry' => true,
            'usedTheme' => 'SwagTheme',
            'databaseSnippets' => [
                'title' => 'Database title',
            ],
        ];
    }

    private function addThemes(): void
    {
        $this->snippetCollection->add(new MockSnippetFile('storefront.de-DE', 'de-DE', '{}', true, 'Storefront'));
        $this->snippetCollection->add(new MockSnippetFile('storefront.en-GB', 'en-GB', '{}', true, 'Storefront'));
        $this->snippetCollection->add(new MockSnippetFile('swagtheme.de-DE', 'de-DE', '{}', true, 'SwagTheme'));
        $this->snippetCollection->add(new MockSnippetFile('swagtheme.en-GB', 'en-GB', '{}', true, 'SwagTheme'));
    }
}
