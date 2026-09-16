<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Snippet;

use Doctrine\DBAL\Connection;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Uri;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Administration\Administration;
use Shopware\Administration\Snippet\SnippetException;
use Shopware\Administration\Snippet\SnippetFinder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\Kernel;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Locale\LocaleCollection;
use Shopware\Core\System\Locale\LocaleDefinition;
use Shopware\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetCollection;
use Shopware\Core\System\Snippet\DataTransfer\Language\Language as LanguageDto;
use Shopware\Core\System\Snippet\DataTransfer\Language\LanguageCollection as LanguageDtoCollection;
use Shopware\Core\System\Snippet\DataTransfer\PluginMapping\PluginMappingCollection;
use Shopware\Core\System\Snippet\Service\TranslationLoader;
use Shopware\Core\System\Snippet\SnippetDefinition;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Storefront\Storefront;
use Shopware\Tests\Unit\Core\System\Snippet\Mock\TestPlugin;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SnippetFinder::class)]
class SnippetFinderTest extends TestCase
{
    use SnippetFileTrait;

    private Filesystem $filesystem;

    /**
     * @var StaticEntityRepository<LanguageCollection>
     */
    private StaticEntityRepository $languageRepository;

    /**
     * @var StaticEntityRepository<LocaleCollection>
     */
    private StaticEntityRepository $localeRepository;

    /**
     * @var StaticEntityRepository<SnippetSetCollection>
     */
    private StaticEntityRepository $snippetSetRepository;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->languageRepository = new StaticEntityRepository([], new LanguageDefinition());
        $this->localeRepository = new StaticEntityRepository([], new LocaleDefinition());
        $this->snippetSetRepository = new StaticEntityRepository([], new SnippetDefinition());
    }

    public function testFindSnippetsFromAppNoSnippetsAdded(): void
    {
        $snippetFinder = $this->getSnippetFinder();

        $snippets = $snippetFinder->findSnippets('en-GB');
        static::assertArrayNotHasKey('my-custom-snippet-key', $snippets);
    }

    public function testFindSnippetsFromApp(): void
    {
        $snippetFinder = $this->getSnippetFinder(
            connection: $this->getConnectionMock($this->getSnippetFixtures())
        );

        $snippets = $snippetFinder->findSnippets('en-GB');

        $expectedSnippets = $this->getSnippetFixtures();
        $key = array_key_first($expectedSnippets);
        static::assertNotNull($key);
        static::assertSame($expectedSnippets[$key], $snippets[$key]);
    }

    public function testNoSnippetsFound(): void
    {
        $snippetFinder = $this->getSnippetFinder(
            connection: $this->getConnectionMock([])
        );

        static::assertEmpty($snippetFinder->findSnippets('fr-FR'));
    }

    public function testDefaultSnippetFileLoading(): void
    {
        $activePluginPaths = [
            'activePlugin',
            'invalidPlugin',
            'nonExistingPlugin',
        ];
        $pluginPaths = [
            'activePlugin',
            'irrelevantPlugin',
        ];
        $bundlePaths = [
            'Administration',
            'Storefront',
            'existingBundle',
            'nonExistingBundle',
        ];

        $snippetFinder = $this->getSnippetFinder(
            $this->getKernelMock($pluginPaths, $activePluginPaths, $bundlePaths),
            $this->getConnectionMock([])
        );

        $actualSnippets = $snippetFinder->findSnippets('jp-JP');

        static::assertEquals([
            'activePlugin' => 'successfully loaded',
            'existingBundle' => 'successfully loaded as well',
            'activeMeteorApp' => 'Snippet',
            'existingBundleMeteorApp' => 'Loaded from a bundle',
        ], $actualSnippets);
    }

    /**
     * @param array<string, mixed> $appSnippets
     */
    #[DataProvider('validAppSnippetsDataProvider')]
    public function testValidateValidSnippets(array $appSnippets): void
    {
        $snippetFinder = $this->getSnippetFinder(
            connection: $this->getConnectionMock($appSnippets)
        );

        $actualSnippetKeys = $snippetFinder->findSnippets('en-GB');
        foreach ($appSnippets as $key => $value) {
            static::assertArrayHasKey($key, $actualSnippetKeys);
        }
    }

    public function testDuplicateAppSnippets(): void
    {
        $testSnippet = ['testSnippetKey' => 'testSnippet'];
        $appSnippets = [
            'sw-category' => $testSnippet,
            'sw-cms' => $testSnippet,
            'sw-wizard' => $testSnippet,
        ];

        $snippetFinderWithoutAppSnippets = $this->getSnippetFinder(
            connection: $this->getConnectionMock([])
        );
        $snippetsWithoutAppSnippets = $snippetFinderWithoutAppSnippets->findSnippets('en-GB');

        $snippetFinderWithAppSnippets = $this->getSnippetFinder(
            connection: $this->getConnectionMock($appSnippets)
        );
        $snippetsWithAppSnippets = $snippetFinderWithAppSnippets->findSnippets('en-GB');

        foreach (array_keys($appSnippets) as $key) {
            static::assertArrayHasKey($key, $snippetsWithoutAppSnippets);
            static::assertArrayNotHasKey('testSnippetKey', $snippetsWithoutAppSnippets[$key]);
            static::assertNotContains('testSnippet', $snippetsWithoutAppSnippets[$key]);

            static::assertArrayHasKey($key, $snippetsWithAppSnippets);
            static::assertArrayHasKey('testSnippetKey', $snippetsWithAppSnippets[$key]);
            static::assertEquals('testSnippet', $snippetsWithAppSnippets[$key]['testSnippetKey']);
        }
    }

    public function testSanitizeAppSnippets(): void
    {
        $snippetFinder = $this->getSnippetFinder(
            connection: $this->getConnectionMock([
                'theme' => [
                    'config' => [
                        'helpText' => '<h1>Summary: </h1> <br> This is a <b>Theme</b>.',
                    ],
                ],
            ]),
        );

        $result = $snippetFinder->findSnippets('en-GB');
        $value = $result['theme']['config']['helpText'];

        static::assertSame('<h1>Summary: </h1> <br> This is a <b>Theme</b>.', $value);
    }

    /**
     * @return iterable<string, array{appSnippets: array<string, mixed>}>
     */
    public static function validAppSnippetsDataProvider(): iterable
    {
        yield 'Everything is valid even with no intersections' => [
            'appSnippets' => [
                'sw-unique-app-key' => [],
            ],
        ];

        /** @var array<string, mixed> $allowedIntersectingFirstLevelSnippets */
        $allowedIntersectingFirstLevelSnippets = array_reduce(
            SnippetFinder::ALLOWED_INTERSECTING_FIRST_LEVEL_SNIPPET_KEYS,
            static function ($accumulator, $value) {
                $accumulator[$value] = [];

                return $accumulator;
            }
        );

        yield 'Everything is valid with duplicates' => [
            'appSnippets' => [
                ...$allowedIntersectingFirstLevelSnippets,
                'sw-unique-app-key' => [],
            ],
        ];
    }

    /**
     * @param list<string> $pluginPaths
     * @param list<string> $activePluginPaths
     * @param list<string> $bundlePaths
     */
    public function getKernelMock(
        array $pluginPaths = [],
        array $activePluginPaths = [],
        array $bundlePaths = []
    ): Kernel&Stub {
        $getBundleMockByPath = static function (string $path): Plugin {
            $path = __DIR__ . '/fixtures/' . $path;

            $plugin = new TestPlugin(true, $path);
            $plugin->setName('activePlugin');
            $plugin->setPath($path);

            return $plugin;
        };

        $plugins = array_map($getBundleMockByPath, $pluginPaths);
        $activePlugins = array_map($getBundleMockByPath, $activePluginPaths);

        $adminBundle = static::createStub(Administration::class);

        $adminBundleFileName = (new \ReflectionClass(Administration::class))->getFileName();
        static::assertNotFalse($adminBundleFileName);

        $adminBundle
            ->method('getPath')
            ->willReturn(\dirname($adminBundleFileName));

        $property = new \ReflectionProperty(Administration::class, 'name');
        $property->setValue($adminBundle, 'Administration');

        $storefrontBundle = static::createStub(Storefront::class);
        $storefrontBundleFileName = (new \ReflectionClass(Storefront::class))->getFileName();
        static::assertNotFalse($storefrontBundleFileName);

        $storefrontBundle
            ->method('getPath')
            ->willReturn(\dirname($storefrontBundleFileName));

        $property = new \ReflectionProperty(Storefront::class, 'name');
        $property->setValue($storefrontBundle, 'Storefront');

        $bundles = [
            ...array_map($getBundleMockByPath, $bundlePaths),
            ...$plugins,
            $adminBundle,
            $storefrontBundle,
        ];

        $pluginCollectionMock = static::createStub(KernelPluginCollection::class);
        $pluginCollectionMock
            ->method('all')
            ->willReturn($plugins);
        $pluginCollectionMock
            ->method('getActives')
            ->willReturn($activePlugins);

        $pluginLoaderMock = static::createStub(KernelPluginLoader::class);
        $pluginLoaderMock
            ->method('getPluginInstances')
            ->willReturn($pluginCollectionMock);

        $kernelMock = static::createStub(Kernel::class);
        $kernelMock
            ->method('getPluginLoader')
            ->willReturn($pluginLoaderMock);
        $kernelMock
            ->method('getBundles')
            ->willReturn($bundles);

        return $kernelMock;
    }

    public function testFindInstalledSnippetsWithoutPluginsActive(): void
    {
        $config = new TranslationConfig(
            new Uri('http://localhost:8000'),
            ['es-ES'],
            [],
            new LanguageDtoCollection([new LanguageDto('es-ES', 'Español')]),
            new PluginMappingCollection(),
            new Uri('http://localhost:8000/metadata.json'),
            ['de-DE'],
        );
        $loader = $this->getTranslationLoader($config);

        $this->createSnippetFixtures($this->filesystem, $loader);

        $snippetFinder = $this->getSnippetFinder(
            connection: $this->getConnectionMock([]),
            translationConfig: $config,
        );

        $snippets = $snippetFinder->findSnippets('es-ES');

        static::assertEquals(['shop_administration' => 'Platform admin'], $snippets);
    }

    public function testFindInstalledSnippetsWithActivePlugin(): void
    {
        $config = new TranslationConfig(
            new Uri('http://localhost:8000'),
            ['es-ES'],
            ['activePlugin'],
            new LanguageDtoCollection([new LanguageDto('es-ES', 'Español')]),
            new PluginMappingCollection(),
            new Uri('http://localhost:8000/metadata.json'),
            ['de-DE'],
        );
        $loader = $this->getTranslationLoader($config);
        $this->createSnippetFixtures($this->filesystem, $loader);

        $pluginPath = __DIR__ . '/_fixtures/activePlugin';
        $snippetFinder = $this->getSnippetFinder(
            kernel: $this->getKernelMock(pluginPaths: [$pluginPath], activePluginPaths: ['activePlugin']),
            connection: $this->getConnectionMock([]),
            translationConfig: $config,
        );

        $snippets = $snippetFinder->findSnippets('es-ES');

        static::assertEquals([
            'plugin_administration' => 'Plugin admin',
            'shop_administration' => 'Platform admin',
        ], $snippets);
    }

    #[TestDox('An invalid snippet file is skipped and logged instead of breaking the administration')]
    public function testInvalidSnippetFileIsSkippedAndLogged(): void
    {
        $config = new TranslationConfig(
            new Uri('http://localhost:8000'),
            ['es-ES'],
            ['activePlugin'],
            new LanguageDtoCollection([new LanguageDto('es-ES', 'Español')]),
            new PluginMappingCollection(),
            new Uri('http://localhost:8000/metadata.json'),
            ['de-DE'],
        );
        $loader = $this->getTranslationLoader($config);
        $this->createSnippetFixtures($this->filesystem, $loader);

        $invalidFilePath = Path::join($loader->getLocalePath('es-ES'), 'Platform', 'administration.json');
        $this->filesystem->write($invalidFilePath, '{');

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('error')
            ->willReturnCallback(function (string $message) use ($invalidFilePath): void {
                $this->assertStringContainsString($invalidFilePath, $message);
            });

        $snippetFinder = $this->getSnippetFinder(
            kernel: $this->getKernelMock(pluginPaths: ['activePlugin'], activePluginPaths: ['activePlugin']),
            connection: $this->getConnectionMock([]),
            translationConfig: $config,
            logger: $logger,
        );

        static::assertSame(
            ['plugin_administration' => 'Plugin admin'],
            $snippetFinder->findSnippets('es-ES'),
            'snippets of intact files must survive an invalid file'
        );
    }

    #[TestDox('An empty snippet file is skipped without logging an error')]
    public function testEmptySnippetFileIsSkipped(): void
    {
        $config = new TranslationConfig(
            new Uri('http://localhost:8000'),
            ['es-ES'],
            ['activePlugin'],
            new LanguageDtoCollection([new LanguageDto('es-ES', 'Español')]),
            new PluginMappingCollection(),
            new Uri('http://localhost:8000/metadata.json'),
            ['de-DE'],
        );
        $loader = $this->getTranslationLoader($config);
        $this->createSnippetFixtures($this->filesystem, $loader);

        $emptyFilePath = Path::join($loader->getLocalePath('es-ES'), 'Platform', 'administration.json');
        $this->filesystem->write($emptyFilePath, '');

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->never())
            ->method('error');

        $snippetFinder = $this->getSnippetFinder(
            kernel: $this->getKernelMock(pluginPaths: ['activePlugin'], activePluginPaths: ['activePlugin']),
            connection: $this->getConnectionMock([]),
            translationConfig: $config,
            logger: $logger,
        );

        static::assertSame(
            ['plugin_administration' => 'Plugin admin'],
            $snippetFinder->findSnippets('es-ES'),
            'snippets of intact files must survive an empty file'
        );
    }

    #[TestDox('In debug mode an invalid snippet file throws an exception naming the file')]
    public function testInvalidSnippetFileThrowsInDebugMode(): void
    {
        $config = new TranslationConfig(
            new Uri('http://localhost:8000'),
            ['es-ES'],
            [],
            new LanguageDtoCollection([new LanguageDto('es-ES', 'Español')]),
            new PluginMappingCollection(),
            new Uri('http://localhost:8000/metadata.json'),
            ['de-DE'],
        );
        $loader = $this->getTranslationLoader($config);
        $this->createSnippetFixtures($this->filesystem, $loader);

        $invalidFilePath = Path::join($loader->getLocalePath('es-ES'), 'Platform', 'administration.json');
        $this->filesystem->write($invalidFilePath, '{');

        $snippetFinder = $this->getSnippetFinder(
            connection: $this->getConnectionMock([]),
            translationConfig: $config,
            debug: true,
        );

        $this->expectExceptionObject(SnippetException::invalidSnippetFile($invalidFilePath, new \JsonException('Syntax error')));

        $snippetFinder->findSnippets('es-ES');
    }

    public function testFinderSkipsExcludedLocales(): void
    {
        $config = new TranslationConfig(
            new Uri('http://localhost:8000'),
            ['es-ES'],
            ['activePlugin'],
            new LanguageDtoCollection([new LanguageDto('es-ES', 'Español')]),
            new PluginMappingCollection(),
            new Uri('http://localhost:8000/metadata.json'),
            ['es-ES'],
        );
        $loader = $this->getTranslationLoader($config);
        $this->createSnippetFixtures($this->filesystem, $loader);

        $pluginPath = __DIR__ . '/_fixtures/activePlugin';
        $snippetFinder = $this->getSnippetFinder(
            kernel: $this->getKernelMock(pluginPaths: [$pluginPath], activePluginPaths: ['activePlugin']),
            connection: $this->getConnectionMock([]),
            translationConfig: $config,
        );

        $snippets = $snippetFinder->findSnippets('es-ES');
        static::assertEmpty($snippets);
    }

    /**
     * @param array<string, mixed> $snippets
     */
    private function getConnectionMock(array $snippets): Connection&Stub
    {
        $connection = static::createStub(Connection::class);

        $returns = [];
        foreach ($snippets as $key => $value) {
            $returns[]['value'] = json_encode([$key => $value], \JSON_THROW_ON_ERROR);
        }

        $connection
            ->method('fetchAllAssociative')
            ->willReturn($returns);

        return $connection;
    }

    /**
     * @return array<string, array<string, array<string, string>>>
     */
    private function getSnippetFixtures(): array
    {
        return [
            'my-custom-snippet-key' => [
                'foo' => [
                    'bar' => 'baz',
                ],
            ],
        ];
    }

    private function getSnippetFinder(
        (Kernel&Stub)|null $kernel = null,
        (Connection&Stub)|null $connection = null,
        ?TranslationConfig $translationConfig = null,
        ?LoggerInterface $logger = null,
        bool $debug = false,
    ): SnippetFinder {
        $config = $translationConfig ?? new TranslationConfig(
            new Uri('http://localhost:8000'),
            ['en-GB'],
            [],
            new LanguageDtoCollection([new LanguageDto('en-GB', 'English (UK')]),
            new PluginMappingCollection(),
            new Uri('http://localhost:8000/metadata.json'),
            ['de-DE'],
        );

        $kernelMock = $kernel ?? $this->getKernelMock();
        $connectionMock = $connection ?? $this->getConnectionMock([]);
        $translationLoader = $this->getTranslationLoader($config);

        $sanitizer = static::createStub(HtmlSanitizer::class);
        $sanitizer->method('sanitize')->willReturnArgument(0);

        return new SnippetFinder(
            $kernelMock,
            $connectionMock,
            $this->filesystem,
            $config,
            $translationLoader,
            $sanitizer,
            $logger ?? new NullLogger(),
            $debug,
        );
    }

    private function getTranslationLoader(
        TranslationConfig $translationConfig,
    ): TranslationLoader {
        return new TranslationLoader(
            translationWriter: $this->filesystem,
            languageRepository: $this->languageRepository,
            localeRepository: $this->localeRepository,
            snippetSetRepository: $this->snippetSetRepository,
            client: static::createStub(ClientInterface::class),
            config: $translationConfig,
            eventDispatcher: new EventDispatcher(),
        );
    }
}
