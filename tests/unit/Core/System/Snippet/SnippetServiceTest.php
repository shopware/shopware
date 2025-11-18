<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet;

use Doctrine\DBAL\Connection;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Uri;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Locale\LocaleCollection;
use Shopware\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetCollection;
use Shopware\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetEntity;
use Shopware\Core\System\Snippet\DataTransfer\Language\Language as LanguageDto;
use Shopware\Core\System\Snippet\DataTransfer\Language\LanguageCollection as LanguageDtoCollection;
use Shopware\Core\System\Snippet\DataTransfer\PluginMapping\PluginMappingCollection;
use Shopware\Core\System\Snippet\Event\SnippetsThemeResolveEvent;
use Shopware\Core\System\Snippet\Files\RemoteSnippetFile;
use Shopware\Core\System\Snippet\Files\SnippetFileCollection;
use Shopware\Core\System\Snippet\Filter\SnippetFilterFactory;
use Shopware\Core\System\Snippet\Service\TranslationLoader;
use Shopware\Core\System\Snippet\SnippetCollection;
use Shopware\Core\System\Snippet\SnippetException;
use Shopware\Core\System\Snippet\SnippetService;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Tests\Unit\Administration\Snippet\SnippetFileTrait;
use Shopware\Tests\Unit\Core\System\Snippet\Mock\MockSnippetFile;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SnippetService::class)]
class SnippetServiceTest extends TestCase
{
    use SnippetFileTrait;

    private SnippetFileCollection $snippetCollection;

    private Connection&MockObject $connection;

    private Flysystem $flysystem;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->flysystem = new Flysystem(new InMemoryFilesystemAdapter(), ['public_url' => 'http://localhost:8000']);
        $this->filesystem = new Filesystem();
        $this->snippetCollection = new SnippetFileCollection();
        $this->addThemes();
    }

    /**
     * @param list<string> $catalogueMessages
     * @param \Throwable|list<string> $expected
     * @param list<string> $databaseSnippets
     */
    #[DataProvider('getStorefrontSnippetsDataProvider')]
    public function testGetStorefrontSnippets(
        array|\Throwable $expected = [],
        false|string $catalogueLocale = 'en-GB',
        array $catalogueMessages = [],
        ?string $fallbackLocale = null,
        ?string $salesChannelId = null,
        ?string $usedTheme = null,
        array $databaseSnippets = []
    ): void {
        if ($expected instanceof \Throwable) {
            $this->expectException($expected::class);
        }

        $this->connection->expects($this->once())->method('fetchOne')->willReturn($catalogueLocale);
        $dispatcher = new EventDispatcher();

        $currentThemeName = $usedTheme ?? 'Storefront';

        $dispatcher->addListener(SnippetsThemeResolveEvent::class, static function (SnippetsThemeResolveEvent $event) use ($currentThemeName): void {
            $activeThemeNames = ['Storefront', 'SwagTheme'];
            $event->setUsedThemes(array_values(array_unique([$currentThemeName, 'Storefront'])));
            $event->setUnusedThemes(array_values(array_diff($activeThemeNames, [$currentThemeName])));
        });

        if ($databaseSnippets !== []) {
            $this->connection->expects($this->once())->method('fetchAllKeyValue')->willReturn($databaseSnippets);
        }

        $catalogue = new MessageCatalogue((string) $catalogueLocale, ['messages' => $catalogueMessages]);
        $snippetService = $this->createSnippetService($dispatcher);
        $snippets = $snippetService->getStorefrontSnippets($catalogue, Uuid::randomHex(), $fallbackLocale, $salesChannelId);

        static::assertEquals($expected, $snippets);
    }

    public function testFindSnippetSetIdWithSalesChannelDomain(): void
    {
        $snippetSetIdWithSalesChannelDomain = Uuid::randomHex();

        $this->connection->expects($this->once())->method('fetchOne')->willReturn($snippetSetIdWithSalesChannelDomain);

        $snippetService = $this->createSnippetService();

        $snippetSetId = $snippetService->findSnippetSetId(Uuid::randomHex(), Uuid::randomHex(), 'en-GB');

        static::assertSame($snippetSetId, $snippetSetIdWithSalesChannelDomain);
    }

    public function testDecodeRemoteSnippets(): void
    {
        $remoteSnippetFile = new RemoteSnippetFile(
            'test',
            '/translation/locale/es-ES/Platform/storefront.json',
            'es-ES',
            'Shopware',
            false,
            'Storefront'
        );

        $this->snippetCollection->add($remoteSnippetFile);

        $config = new TranslationConfig(
            new Uri('http://localhost:8000'),
            ['es-ES'],
            [],
            new LanguageDtoCollection([new LanguageDto('es-ES', 'Español')]),
            new PluginMappingCollection(),
            new Uri('http://localhost:8000/metadata.json'),
            ['en-GB'],
        );

        $loader = $this->getTranslationLoader($config);
        $this->createSnippetFixtures($this->flysystem, $loader);

        $this->connection->expects($this->once())
            ->method('fetchOne')->willReturn('es-ES');

        $snippetService = $this->createSnippetService();

        $catalogue = new MessageCatalogue('es', ['messages' => []]);
        $snippets = $snippetService->getStorefrontSnippets($catalogue, Uuid::randomHex(), 'es-ES', Uuid::randomHex());

        static::assertSame(['shop_storefront' => 'Platform storefront'], $snippets);
    }

    /**
     * @param array<string, string> $sets
     */
    #[DataProvider('findSnippetSetIdDataProvider')]
    public function testFindSnippetSetIdWithoutSalesChannelDomain(array $sets, string $expected): void
    {
        $this->connection->expects($this->once())->method('fetchOne')->willReturn(null);
        $this->connection->expects($this->once())->method('fetchAllKeyValue')->willReturn($sets);

        $snippetService = $this->createSnippetService();

        $snippetSetId = $snippetService->findSnippetSetId(Uuid::randomHex(), Uuid::randomHex(), 'vi-VN');

        static::assertSame($snippetSetId, $expected);
    }

    public static function findSnippetSetIdDataProvider(): \Generator
    {
        $snippetSetIdWithVI = Uuid::randomHex();
        $snippetSetIdWithEN = Uuid::randomHex();

        yield 'get snippet set with locale vi-VN' => [
            'sets' => [
                'vi-VN' => $snippetSetIdWithVI,
                'en-GB' => $snippetSetIdWithEN,
            ],
            'expected' => $snippetSetIdWithVI,
        ];

        yield 'get snippet set without locale vi-VN' => [
            'sets' => [
                'en-GB' => $snippetSetIdWithEN,
            ],
            'expected' => $snippetSetIdWithEN,
        ];
    }

    public static function getStorefrontSnippetsDataProvider(): \Generator
    {
        yield 'with unknown snippet id' => [
            'expected' => SnippetException::snippetSetNotFound('test'),
            'catalogueLocale' => false,
            'catalogueMessages' => [],
            'fallbackLocale' => null,
            'salesChannelId' => null,
        ];

        yield 'with messages from catalogue' => [
            'expected' => [
                'catalogue_key' => 'Catalogue EN',
            ],
            'catalogueLocale' => 'en-GB',
            'catalogueMessages' => [
                'catalogue_key' => 'Catalogue EN',
            ],
        ];

        yield 'fallback snippets are used if no localized snippet found' => [
            'expected' => [
                'title' => 'Storefront EN',
            ],
            'catalogueLocale' => 'vi',
            'catalogueMessages' => [],
            'fallbackLocale' => 'en',
        ];

        yield 'fallback snippets are overridden by catalogue messages' => [
            'expected' => [
                'catalogue_key' => 'Catalogue VI',
                'title' => 'Catalogue title VI',
            ],
            'catalogueLocale' => 'vi',
            'catalogueMessages' => [
                'catalogue_key' => 'Catalogue VI',
                'title' => 'Catalogue title VI',
            ],
            'fallbackLocale' => 'en',
        ];

        yield 'fallback snippets, catalogue messages are overridden by localized snippets' => [
            'expected' => [
                'catalogue_key' => 'Catalogue DE',
                'title' => 'Storefront DE',
            ],
            'catalogueLocale' => 'de',
            'catalogueMessages' => [
                'catalogue_key' => 'Catalogue DE',
                'title' => 'Catalogue title DE',
            ],
            'fallbackLocale' => 'en',
        ];

        yield 'fallback snippets, catalogue message, localized snippets are overridden by database snippets' => [
            'expected' => [
                'title' => 'Database title',
                'catalogue_key' => 'Catalogue DE',
            ],
            'catalogueLocale' => 'de-DE',
            'catalogueMessages' => [
                'catalogue_key' => 'Catalogue DE',
                'title' => 'Catalogue title',
            ],
            'fallbackLocale' => 'de',
            'salesChannelId' => null,
            'usedTheme' => null,
            'databaseSnippets' => [
                'title' => 'Database title',
            ],
        ];

        yield 'with sales channel id without theme' => [
            'expected' => [
                'title' => 'Storefront DE',
            ],
            'catalogueLocale' => 'de-DE',
            'catalogueMessages' => [],
            'fallbackLocale' => 'de',
            'salesChannelId' => Uuid::randomHex(),
            'usedTheme' => null,
            'databaseSnippets' => [],
        ];

        yield 'with sales channel id and theme' => [
            'expected' => [
                'title' => 'SwagTheme DE',
            ],
            'catalogueLocale' => 'de-DE',
            'catalogueMessages' => [],
            'fallbackLocale' => 'de',
            'salesChannelId' => Uuid::randomHex(),
            'usedTheme' => 'SwagTheme',
        ];

        yield 'theme snippets are overridden by database snippets' => [
            'expected' => [
                'title' => 'Database title',
                'catalogue_key' => 'Catalogue DE',
            ],
            'catalogueLocale' => 'de-DE',
            'catalogueMessages' => [
                'catalogue_key' => 'Catalogue DE',
                'title' => 'Catalogue title',
            ],
            'fallbackLocale' => 'de',
            'salesChannelId' => Uuid::randomHex(),
            'usedTheme' => 'SwagTheme',
            'databaseSnippets' => [
                'title' => 'Database title',
            ],
        ];
    }

    /**
     * @param array<string,string> $expectedSnippets
     */
    #[DataProvider('getListDataProvider')]
    public function testGetList(string $iso, array $expectedSnippets): void
    {
        $availableFixtures = [
            'agnostic.es',
            'country.es-AR',
            'country.fr-CA',
            'agnostic.zh',
            'country.zh-Hans-CN',
        ];

        $baseIso = \explode('-', $iso, 2)[0];

        $baseFileName = 'agnostic.' . $baseIso;
        $countryFileName = 'country.' . $iso;

        $snippetCollection = new SnippetFileCollection();
        // only add files that exist in fixtures list
        if (\in_array($baseFileName, $availableFixtures, true)) {
            $snippetCollection->add(new MockSnippetFile($baseFileName, $baseIso));
        }

        if (\in_array($countryFileName, $availableFixtures, true)) {
            $snippetCollection->add(new MockSnippetFile($countryFileName, $iso));
        }

        // Create snippet set entity representing available snippet set
        $snippetSet = new SnippetSetEntity();
        $setId = Uuid::randomHex();
        $snippetSet->setId($setId);
        $snippetSet->setIso($iso);
        $snippetSet->setName('test');
        $snippetSet->setBaseFile($countryFileName . '.json');

        $snippetSetCollection = new SnippetSetCollection();
        $snippetSetCollection->add($snippetSet);

        /** @var StaticEntityRepository<SnippetSetCollection> $snippetSetRepository */
        $snippetSetRepository = new StaticEntityRepository([
            static function ($criteria, $context) use ($snippetSetCollection) {
                return $snippetSetCollection;
            },
        ]);
        /** @var StaticEntityRepository<SnippetCollection> $snippetRepository */
        $snippetRepository = new StaticEntityRepository([
            static function ($criteria, $context) {
                return new SnippetCollection();
            },
        ]);

        $service = $this->createSnippetService(
            snippetRepository: $snippetRepository,
            snippetSetRepository: $snippetSetRepository,
            snippetFileCollection: $snippetCollection,
            connection: $this->connection,
        );

        $context = Context::createDefaultContext();
        $result = $service->getList(1, 10, $context, [], []);

        // Assert the total count matches the number of expected translation keys
        static::assertSame(\count($expectedSnippets), $result['total']);

        // Assert each expected translation key is present and has the correct value
        foreach ($expectedSnippets as $translationKey => $value) {
            static::assertArrayHasKey($translationKey, $result['data']);
            static::assertSame($value, $result['data'][$translationKey][0]['value']);
        }
    }

    /**
     * Data provider for getList tests.
     */
    public static function getListDataProvider(): \Generator
    {
        yield 'agnostic locale es without country' => [
            'iso' => 'es',
            'expectedSnippets' => [
                'title' => 'Agnostic ES',
                'baseOnly' => 'Agnostic ES',
            ],
        ];

        yield 'es-AR iso falls back to es' => [
            'iso' => 'es-AR',
            'expectedSnippets' => [
                'title' => 'Country es-AR',
                'baseOnly' => 'Agnostic ES',
            ],
        ];

        yield 'country exists without base' => [
            'iso' => 'fr-CA',
            'expectedSnippets' => [
                'title' => 'Country fr-CA',
            ],
        ];

        yield 'country es-EM does not exist - only base es exists' => [
            'iso' => 'es-EM',
            'expectedSnippets' => [
                'title' => 'Agnostic ES',
                'baseOnly' => 'Agnostic ES',
            ],
        ];
    }

    private function addThemes(): void
    {
        $this->snippetCollection->add(new MockSnippetFile('storefront.de', 'de', '{}', true, 'Storefront'));
        $this->snippetCollection->add(new MockSnippetFile('storefront.en', 'en', '{}', true, 'Storefront'));
        $this->snippetCollection->add(new MockSnippetFile('swagtheme.de', 'de', '{}', true, 'SwagTheme'));
        $this->snippetCollection->add(new MockSnippetFile('swagtheme.en', 'en', '{}', true, 'SwagTheme'));
    }

    /**
     * All parameters are optional. When provided they override defaults.
     *
     * @param StaticEntityRepository<SnippetCollection>|null $snippetRepository
     * @param StaticEntityRepository<SnippetSetCollection>|null $snippetSetRepository
     */
    private function createSnippetService(
        ?EventDispatcherInterface $eventDispatcher = null,
        ?EntityRepository $snippetRepository = null,
        ?EntityRepository $snippetSetRepository = null,
        ?SnippetFileCollection $snippetFileCollection = null,
        ?Connection $connection = null,
        ?SnippetFilterFactory $snippetFilterFactory = null,
        ?ExtensionDispatcher $extensionDispatcher = null
    ): SnippetService {
        if ($snippetRepository === null) {
            $snippetRepository = new StaticEntityRepository([]);
        }

        if ($snippetSetRepository === null) {
            $snippetSetRepository = new StaticEntityRepository([]);
        }

        $snippetFileCollection = $snippetFileCollection ?? $this->snippetCollection;
        $connection = $connection ?? $this->connection;
        $snippetFilterFactory = $snippetFilterFactory ?? $this->createMock(SnippetFilterFactory::class);
        $extensionDispatcher = $extensionDispatcher ?? new ExtensionDispatcher(new EventDispatcher());

        /** @var EntityRepository<SnippetCollection> $snippetRepository */
        /** @var EntityRepository<SnippetSetCollection> $snippetSetRepository */
        return new SnippetService(
            $connection,
            $snippetFileCollection,
            $snippetRepository,
            $snippetSetRepository,
            $snippetFilterFactory,
            $extensionDispatcher,
            $eventDispatcher ?? new EventDispatcher(),
            $this->flysystem,
            $this->filesystem,
        );
    }

    private function getTranslationLoader(TranslationConfig $config): TranslationLoader
    {
        /** @var StaticEntityRepository<LanguageCollection> $languageRepository */
        $languageRepository = new StaticEntityRepository([]);

        /** @var StaticEntityRepository<LocaleCollection> $localeRepository */
        $localeRepository = new StaticEntityRepository([]);

        /** @var StaticEntityRepository<SnippetSetCollection> $snippetSetRepository */
        $snippetSetRepository = new StaticEntityRepository([]);

        return new TranslationLoader(
            translationWriter: $this->flysystem,
            languageRepository: $languageRepository,
            localeRepository: $localeRepository,
            snippetSetRepository: $snippetSetRepository,
            client: $this->createMock(ClientInterface::class),
            config: $config,
            validator: Validation::createValidator(),
        );
    }
}
