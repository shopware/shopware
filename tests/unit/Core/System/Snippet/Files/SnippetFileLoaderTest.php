<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Files;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Uri;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\App\Lifecycle\AppLoader;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Core\Kernel;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Locale\LocaleCollection;
use Shopware\Core\System\Locale\LocaleDefinition;
use Shopware\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetCollection;
use Shopware\Core\System\Snippet\DataTransfer\Language\Language as LanguageDto;
use Shopware\Core\System\Snippet\DataTransfer\Language\LanguageCollection as LanguageDtoCollection;
use Shopware\Core\System\Snippet\DataTransfer\PluginMapping\PluginMappingCollection;
use Shopware\Core\System\Snippet\Files\AppSnippetFileLoader;
use Shopware\Core\System\Snippet\Files\GenericSnippetFile;
use Shopware\Core\System\Snippet\Files\RemoteSnippetFile;
use Shopware\Core\System\Snippet\Files\SnippetFileCollection;
use Shopware\Core\System\Snippet\Files\SnippetFileLoader;
use Shopware\Core\System\Snippet\Service\TranslationLoader;
use Shopware\Core\System\Snippet\SnippetDefinition;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Tests\Unit\Administration\Snippet\SnippetFileTrait;
use Shopware\Tests\Unit\Core\System\Snippet\Files\_fixtures\BaseSnippetSet\BaseSnippetSet;
use Shopware\Tests\Unit\Core\System\Snippet\Files\_fixtures\ShopwareBundleWithSnippets\ShopwareBundleWithSnippets;
use Shopware\Tests\Unit\Core\System\Snippet\Files\_fixtures\SnippetSet\SnippetSet;
use Shopware\Tests\Unit\Core\System\Snippet\Mock\TestPlugin;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[CoversClass(SnippetFileLoader::class)]
class SnippetFileLoaderTest extends TestCase
{
    use SnippetFileTrait;

    private TranslationConfig $config;

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
        $this->config = new TranslationConfig(
            new Uri('http://localhost:8000'),
            ['es-ES'],
            ['activePlugin'],
            new LanguageDtoCollection([new LanguageDto('es-ES', 'Español')]),
            new PluginMappingCollection()
        );
    }

    public function testLoadSnippetsFromShopwareBundle(): void
    {
        $kernel = $this->getKernel([
            'ShopwareBundleWithSnippets' => new ShopwareBundleWithSnippets(),
        ]);

        $collection = new SnippetFileCollection();

        $snippetFileLoader = new SnippetFileLoader(
            $kernel,
            $this->createMock(Connection::class),
            $this->createMock(AppSnippetFileLoader::class),
            new ActiveAppsLoader(
                $this->createMock(Connection::class),
                $this->createMock(AppLoader::class),
                '/'
            ),
            $this->config,
            $this->getTranslationLoader(),
            $this->filesystem
        );

        $snippetFileLoader->loadSnippetFilesIntoCollection($collection);

        static::assertCount(2, $collection);

        $snippetFile = $collection->getSnippetFilesByIso('de-DE')[0];
        static::assertSame('storefront.de-DE', $snippetFile->getName());
        static::assertSame(
            __DIR__ . '/_fixtures/ShopwareBundleWithSnippets/Resources/snippet/storefront.de-DE.json',
            $snippetFile->getPath()
        );
        static::assertSame('de-DE', $snippetFile->getIso());
        static::assertSame('Shopware', $snippetFile->getAuthor());
        static::assertFalse($snippetFile->isBase());

        $snippetFile = $collection->getSnippetFilesByIso('en-GB')[0];
        static::assertSame('storefront.en-GB', $snippetFile->getName());
        static::assertSame(
            __DIR__ . '/_fixtures/ShopwareBundleWithSnippets/Resources/snippet/storefront.en-GB.json',
            $snippetFile->getPath()
        );
        static::assertSame('en-GB', $snippetFile->getIso());
        static::assertSame('Shopware', $snippetFile->getAuthor());
        static::assertSame('ShopwareBundleWithSnippets', $snippetFile->getTechnicalName());
        static::assertFalse($snippetFile->isBase());
    }

    public function testLoadSnippetFilesIntoCollectionDoesNotOverwriteFiles(): void
    {
        $kernel = $this->getKernel([
            'ShopwareBundleWithSnippets' => new ShopwareBundleWithSnippets(),
        ]);

        $collection = new SnippetFileCollection([
            new GenericSnippetFile(
                'test',
                __DIR__ . '/_fixtures/ShopwareBundleWithSnippets/Resources/snippet/storefront.de-DE.json',
                'xx-XX',
                'test Author',
                true,
                'ShopwareBundleWithSnippets',
            ),
            new GenericSnippetFile(
                'test',
                __DIR__ . '/_fixtures/ShopwareBundleWithSnippets/Resources/snippet/storefront.en-GB.json',
                'yy-YY',
                'test Author',
                true,
                'ShopwareBundleWithSnippets',
            ),
        ]);

        $snippetFileLoader = new SnippetFileLoader(
            $kernel,
            $this->createMock(Connection::class),
            $this->createMock(AppSnippetFileLoader::class),
            new ActiveAppsLoader(
                $this->createMock(Connection::class),
                $this->createMock(AppLoader::class),
                '/'
            ),
            $this->config,
            $this->getTranslationLoader(),
            $this->filesystem
        );

        $snippetFileLoader->loadSnippetFilesIntoCollection($collection);

        static::assertCount(2, $collection);

        $snippetFile = $collection->getSnippetFilesByIso('xx-XX')[0];
        static::assertSame('test', $snippetFile->getName());
        static::assertSame(
            __DIR__ . '/_fixtures/ShopwareBundleWithSnippets/Resources/snippet/storefront.de-DE.json',
            $snippetFile->getPath()
        );
        static::assertSame('xx-XX', $snippetFile->getIso());
        static::assertSame('test Author', $snippetFile->getAuthor());
        static::assertTrue($snippetFile->isBase());

        $snippetFile = $collection->getSnippetFilesByIso('yy-YY')[0];
        static::assertSame('test', $snippetFile->getName());
        static::assertSame(
            __DIR__ . '/_fixtures/ShopwareBundleWithSnippets/Resources/snippet/storefront.en-GB.json',
            $snippetFile->getPath()
        );
        static::assertSame('yy-YY', $snippetFile->getIso());
        static::assertSame('test Author', $snippetFile->getAuthor());
        static::assertSame('ShopwareBundleWithSnippets', $snippetFile->getTechnicalName());
        static::assertTrue($snippetFile->isBase());
    }

    public function testLoadSnippetsFromPlugin(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchAllKeyValue')->willReturn([
            SnippetSet::class => 'Plugin Manufacturer',
        ]);

        $kernel = $this->getKernel([
            'SnippetSet' => new SnippetSet(true, __DIR__),
        ]);

        $collection = new SnippetFileCollection();

        $snippetFileLoader = new SnippetFileLoader(
            $kernel,
            $connection,
            $this->createMock(AppSnippetFileLoader::class),
            new ActiveAppsLoader(
                $this->createMock(Connection::class),
                $this->createMock(AppLoader::class),
                '/'
            ),
            $this->config,
            $this->getTranslationLoader(),
            $this->filesystem
        );

        $snippetFileLoader->loadSnippetFilesIntoCollection($collection);

        static::assertCount(2, $collection);

        $snippetFile = $collection->getSnippetFilesByIso('de-DE')[0];
        static::assertSame('storefront.de-DE', $snippetFile->getName());
        static::assertSame(
            __DIR__ . '/_fixtures/SnippetSet/Resources/snippet/storefront.de-DE.json',
            $snippetFile->getPath()
        );
        static::assertSame('de-DE', $snippetFile->getIso());
        static::assertSame('Plugin Manufacturer', $snippetFile->getAuthor());
        static::assertFalse($snippetFile->isBase());

        $snippetFile = $collection->getSnippetFilesByIso('en-GB')[0];
        static::assertSame('storefront.en-GB', $snippetFile->getName());
        static::assertSame(
            __DIR__ . '/_fixtures/SnippetSet/Resources/snippet/storefront.en-GB.json',
            $snippetFile->getPath()
        );
        static::assertSame('en-GB', $snippetFile->getIso());
        static::assertSame('Plugin Manufacturer', $snippetFile->getAuthor());
        static::assertSame('SnippetSet', $snippetFile->getTechnicalName());
        static::assertFalse($snippetFile->isBase());
    }

    public function testLoadAppSnippets(): void
    {
        $snippetFile = new GenericSnippetFile(
            'TestApp',
            '/test/app/path',
            'es-ES',
            'Test Author',
            false,
            'TestApp',
        );

        $appSnippetFileLoader = $this->createMock(AppSnippetFileLoader::class);
        $appSnippetFileLoader->expects($this->once())
            ->method('loadSnippetFilesFromApp')
            ->with('Test Author', '/test/app/path')
            ->willReturn([$snippetFile]);

        $activeAppsLoader = $this->createMock(ActiveAppsLoader::class);
        $activeAppsLoader->expects($this->once())
            ->method('getActiveApps')
            ->willReturn([
                [
                    'name' => 'TestApp',
                    'author' => 'Test Author',
                    'path' => '/test/app/path',
                ],
            ]);

        $collection = new SnippetFileCollection();

        $snippetFileLoader = new SnippetFileLoader(
            $this->createMock(Kernel::class),
            $this->createMock(Connection::class),
            $appSnippetFileLoader,
            $activeAppsLoader,
            $this->config,
            $this->getTranslationLoader(),
            $this->filesystem
        );

        $snippetFileLoader->loadSnippetFilesIntoCollection($collection);

        static::assertCount(1, $collection);
    }

    public function testLoadBaseSnippetsFromPlugin(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchAllKeyValue')->willReturn([
            BaseSnippetSet::class => 'Plugin Manufacturer',
        ]);

        $kernel = $this->getKernel([
            'BaseSnippetSet' => new BaseSnippetSet(true, __DIR__),
        ]);

        $collection = new SnippetFileCollection();

        $snippetFileLoader = new SnippetFileLoader(
            $kernel,
            $connection,
            $this->createMock(AppSnippetFileLoader::class),
            new ActiveAppsLoader(
                $this->createMock(Connection::class),
                $this->createMock(AppLoader::class),
                '/'
            ),
            $this->config,
            $this->getTranslationLoader(),
            $this->filesystem
        );

        $snippetFileLoader->loadSnippetFilesIntoCollection($collection);

        static::assertCount(4, $collection);
        static::assertCount(2, $collection->getSnippetFilesByIso('de-DE'));

        $snippetFile = $collection->getByName('de-DE');
        static::assertInstanceOf(GenericSnippetFile::class, $snippetFile);
        static::assertSame('de-DE', $snippetFile->getName());
        static::assertSame(
            __DIR__ . '/_fixtures/BaseSnippetSet/Resources/app/administration/src/module/sw-module/snippet/de-DE.json',
            $snippetFile->getPath()
        );
        static::assertSame('de-DE', $snippetFile->getIso());
        static::assertSame('Plugin Manufacturer', $snippetFile->getAuthor());
        static::assertSame('BaseSnippetSet', $snippetFile->getTechnicalName());
        static::assertFalse($snippetFile->isBase());

        $snippetFile = $collection->getByName('storefront.de-DE');
        static::assertInstanceOf(GenericSnippetFile::class, $snippetFile);
        static::assertSame('storefront.de-DE', $snippetFile->getName());
        static::assertSame(
            __DIR__ . '/_fixtures/BaseSnippetSet/Resources/snippet/storefront.de-DE.base.json',
            $snippetFile->getPath()
        );
        static::assertSame('de-DE', $snippetFile->getIso());
        static::assertSame('Plugin Manufacturer', $snippetFile->getAuthor());
        static::assertSame('BaseSnippetSet', $snippetFile->getTechnicalName());
        static::assertTrue($snippetFile->isBase());

        static::assertCount(2, $collection->getSnippetFilesByIso('en-GB'));

        $snippetFile = $collection->getByName('en-GB');
        static::assertInstanceOf(GenericSnippetFile::class, $snippetFile);
        static::assertSame('en-GB', $snippetFile->getName());
        static::assertSame(
            __DIR__ . '/_fixtures/BaseSnippetSet/Resources/app/administration/src/module/sw-module/snippet/en-GB.json',
            $snippetFile->getPath()
        );
        static::assertSame('en-GB', $snippetFile->getIso());
        static::assertSame('Plugin Manufacturer', $snippetFile->getAuthor());
        static::assertSame('BaseSnippetSet', $snippetFile->getTechnicalName());
        static::assertFalse($snippetFile->isBase());

        $snippetFile = $collection->getByName('storefront.en-GB');
        static::assertInstanceOf(GenericSnippetFile::class, $snippetFile);
        static::assertSame('storefront.en-GB', $snippetFile->getName());
        static::assertSame(
            __DIR__ . '/_fixtures/BaseSnippetSet/Resources/snippet/storefront.en-GB.base.json',
            $snippetFile->getPath()
        );
        static::assertSame('en-GB', $snippetFile->getIso());
        static::assertSame('Plugin Manufacturer', $snippetFile->getAuthor());
        static::assertTrue($snippetFile->isBase());
    }

    public function testLoadInstalledCoreAndPluginSnippets(): void
    {
        $loader = $this->getTranslationLoader();
        $this->createSnippetFixtures($this->filesystem, $loader);

        $path = __DIR__ . '/_fixtures/activePlugin';

        $plugin = new TestPlugin(true, $path);
        $plugin->setName('activePlugin');
        $plugin->setPath($path);

        $kernel = $this->getKernel([], $plugin);
        $this->config = new TranslationConfig(
            new Uri('http://localhost:8000'),
            ['es-ES'],
            ['activePlugin', 'inactivePlugin'],
            new LanguageDtoCollection([new LanguageDto('es-ES', 'Español')]),
            new PluginMappingCollection()
        );

        $collection = new SnippetFileCollection();

        $snippetFileLoader = new SnippetFileLoader(
            $kernel,
            $this->createMock(Connection::class),
            $this->createMock(AppSnippetFileLoader::class),
            new ActiveAppsLoader(
                $this->createMock(Connection::class),
                $this->createMock(AppLoader::class),
                '/',
            ),
            $this->config,
            $loader,
            $this->filesystem
        );

        $snippetFileLoader->loadSnippetFilesIntoCollection($collection);
        static::assertCount(6, $collection);

        $files = $collection->getElements();
        static::assertContainsOnlyInstancesOf(RemoteSnippetFile::class, $files);

        $platformPath = Path::join($loader->getLocalePath('es-ES'), 'Platform');
        $platformPath = mb_ltrim($platformPath, '/\\');
        $activePluginPath = Path::join($loader->getLocalePath('es-ES'), 'Plugins', 'activePlugin');
        $activePluginPath = mb_ltrim($activePluginPath, '/\\');
        $actualPaths = array_map(static fn (RemoteSnippetFile $file) => $file->getPath(), $files);

        $expectedPaths = [
            Path::join($platformPath, 'storefront.json'),
            Path::join($platformPath, 'messages.es-ES.base.json'),
            Path::join($platformPath, 'administration.json'),
            Path::join($activePluginPath, 'storefront.json'),
            Path::join($activePluginPath, 'messages.es-ES.base.json'),
            Path::join($activePluginPath, 'administration.json'),
        ];

        sort($actualPaths);
        sort($expectedPaths);

        static::assertSame($expectedPaths, $actualPaths);
    }

    public function testLoadLegacySnippetsHandlesDatabaseException(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllKeyValue')->willThrowException(new QueryException('Query failed'));

        $kernel = $this->getKernel([
            'ShopwareBundleWithSnippets' => new ShopwareBundleWithSnippets(),
        ]);

        $collection = new SnippetFileCollection();

        $snippetFileLoader = new SnippetFileLoader(
            $kernel,
            $connection,
            $this->createMock(AppSnippetFileLoader::class),
            new ActiveAppsLoader(
                $this->createMock(Connection::class),
                $this->createMock(AppLoader::class),
                '/'
            ),
            $this->config,
            $this->getTranslationLoader(),
            $this->filesystem
        );

        $snippetFileLoader->loadSnippetFilesIntoCollection($collection);

        static::assertCount(2, $collection);

        // Verify author falls back to 'Shopware' for bundles when DB fails
        $snippetFile = $collection->getSnippetFilesByIso('de-DE')[0];
        static::assertSame('Shopware', $snippetFile->getAuthor());
    }

    public function testLoadLegacySnippetsSkipsNonBundleObjects(): void
    {
        $kernel = $this->createMock(Kernel::class);
        $kernel->method('getBundles')->willReturn([
            'NonBundle' => new \stdClass(),
        ]);

        $collection = new SnippetFileCollection();

        $snippetFileLoader = new SnippetFileLoader(
            $kernel,
            $this->createMock(Connection::class),
            $this->createMock(AppSnippetFileLoader::class),
            new ActiveAppsLoader(
                $this->createMock(Connection::class),
                $this->createMock(AppLoader::class),
                '/'
            ),
            $this->config,
            $this->getTranslationLoader(),
            $this->filesystem
        );

        $snippetFileLoader->loadSnippetFilesIntoCollection($collection);

        static::assertCount(0, $collection);
    }

    public function testLoadLegacySnippetsSkipsAdministrationBundle(): void
    {
        $plugin = new TestPlugin(true, '');
        $plugin->setPath('/fake/admin/path');
        $plugin->setName('TestPlugin');

        $loader = $this->getTranslationLoader();

        $pluginPath = Path::join($loader->getLocalePath('es-ES'), 'Plugins', $plugin->getName());
        $this->filesystem->createDirectory($pluginPath);

        $kernel = $this->createMock(Kernel::class);
        $kernel->method('getBundles')->willReturn([
            $plugin->getName() => $plugin,
        ]);

        $collection = new SnippetFileCollection();

        $snippetFileLoader = new SnippetFileLoader(
            $kernel,
            $this->createMock(Connection::class),
            $this->createMock(AppSnippetFileLoader::class),
            new ActiveAppsLoader(
                $this->createMock(Connection::class),
                $this->createMock(AppLoader::class),
                '/'
            ),
            $this->config,
            $loader,
            $this->filesystem
        );

        $snippetFileLoader->loadSnippetFilesIntoCollection($collection);

        static::assertCount(0, $collection);
    }

    public function testLoadCoreSnippetsSkipsInvalidPathStructure(): void
    {
        $this->filesystem->write('locales/invalid-path/file.json', '{}');

        $translationLoader = $this->createMock(TranslationLoader::class);
        $translationLoader->method('getLocalesBasePath')->willReturn('locales');

        $collection = new SnippetFileCollection();

        $snippetFileLoader = new SnippetFileLoader(
            $this->createMock(Kernel::class),
            $this->createMock(Connection::class),
            $this->createMock(AppSnippetFileLoader::class),
            new ActiveAppsLoader(
                $this->createMock(Connection::class),
                $this->createMock(AppLoader::class),
                '/'
            ),
            $this->config,
            $translationLoader,
            $this->filesystem
        );

        $snippetFileLoader->loadSnippetFilesIntoCollection($collection);

        // Should be empty because the invalid path structure was skipped
        static::assertCount(0, $collection);
    }

    /**
     * @param array<string, Bundle> $bundles
     */
    private function getKernel(array $bundles, ?Plugin $plugin = null): MockedKernel
    {
        $pluginCollection = new KernelPluginCollection();

        if ($plugin) {
            $pluginCollection->add($plugin);
        }

        $pluginLoader = $this->createMock(KernelPluginLoader::class);
        $pluginLoader->method('getPluginInstances')->willReturn($pluginCollection);

        return new MockedKernel($bundles, $pluginLoader);
    }

    private function getTranslationLoader(): TranslationLoader
    {
        return new TranslationLoader(
            translationWriter: $this->filesystem,
            languageRepository: $this->languageRepository,
            localeRepository: $this->localeRepository,
            snippetSetRepository: $this->snippetSetRepository,
            client: $this->createMock(ClientInterface::class),
            config: $this->config,
            validator: Validation::createValidator(),
        );
    }
}
