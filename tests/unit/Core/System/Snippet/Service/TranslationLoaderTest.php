<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Service;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use League\Flysystem\Filesystem as FlySystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Locale\LocaleCollection;
use Shopware\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetCollection;
use Shopware\Core\System\Snippet\DataTransfer\Language\Language;
use Shopware\Core\System\Snippet\DataTransfer\Language\LanguageCollection as LanguageDtoCollection;
use Shopware\Core\System\Snippet\DataTransfer\PluginMapping\PluginMapping;
use Shopware\Core\System\Snippet\DataTransfer\PluginMapping\PluginMappingCollection;
use Shopware\Core\System\Snippet\Service\TranslationLoader;
use Shopware\Core\System\Snippet\SnippetException;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Tests\Unit\Core\System\Snippet\Mock\TestPlugin;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(TranslationLoader::class)]
class TranslationLoaderTest extends TestCase
{
    private ClientInterface&MockObject $client;

    private FlySystem $flysystem;

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

    private IdsCollection $ids;

    private Context $context;

    private TranslationConfig $config;

    protected function setUp(): void
    {
        $this->client = $this->createMock(ClientInterface::class);
        $this->flysystem = new FlySystem(new InMemoryFilesystemAdapter(), ['public_url' => 'http://localhost:8000']);
        $this->context = Context::createDefaultContext();
        $this->ids = new IdsCollection();
        $this->languageRepository = new StaticEntityRepository([$this->getSearchResult('language')]);
        $this->localeRepository = new StaticEntityRepository([$this->getSearchResult('locale')]);
        $this->snippetSetRepository = new StaticEntityRepository([$this->getSearchResult('snippet-set')]);
        $this->config = new TranslationConfig(
            new Uri('http://localhost:8000'),
            ['es-ES'],
            ['SwagPublisher'],
            new LanguageDtoCollection([new Language('es-ES', 'Español')]),
            new PluginMappingCollection(),
            new Uri('http://localhost:8000/metadata.json'),
        );
        $this->initClient();
    }

    public function testLoadThrowsExceptionIfLanguageDoesNotExist(): void
    {
        $loader = $this->getTranslationLoader();

        static::expectException(SnippetException::class);
        $loader->load('non-existent-language', $this->context);
    }

    public function testLoadThrowsExceptionIfProvidedLocaleDoesNotExist(): void
    {
        $this->localeRepository = new StaticEntityRepository([$this->getEmptySearchResult()]);

        $loader = $this->getTranslationLoader();

        static::expectException(SnippetException::class);
        static::expectExceptionMessage('The configured locale "es-ES" does not exist.');
        $loader->load('es-ES', $this->context);
    }

    public function testLoadThrowsExceptionIfRemoteServerReturnsNon404(): void
    {
        $response500 = new Response(500);
        $request = new Request('GET', 'http://localhost:8000');
        $requestException = new RequestException('Server Error', $request, $response500);

        $this->client = $this->createMock(ClientInterface::class);
        $this->client->method('request')->willThrowException($requestException);

        $loader = $this->getTranslationLoader();

        static::expectException(GuzzleException::class);
        static::expectExceptionCode(500);
        $loader->load('es-ES', $this->context);
    }

    public function testLoadSkips404RemoteFiles(): void
    {
        $response404 = new Response(404);
        $request = new Request('GET', 'http://localhost:8000');
        $requestException = new RequestException('Not Found', $request, $response404);

        $this->client = $this->createMock(ClientInterface::class);
        $this->client->method('request')->willReturnCallback(function ($method, $url) use ($requestException) {
            if (str_contains($url, 'administration.json')) {
                throw $requestException;
            }
            $jsonResponse = json_encode(['es-ES']);
            static::assertIsString($jsonResponse);

            return new Response(200, [], $jsonResponse);
        });

        $loader = $this->getTranslationLoader();
        $loader->load('es-ES', $this->context);

        $writtenFiles = $this->flysystem->listContents(TranslationLoader::TRANSLATION_DIR, true)
            ->filter(fn ($item) => $item->isFile())
            ->map(fn ($item) => $item->path())
            ->toArray();

        static::assertCount(3, $writtenFiles);
        foreach ($writtenFiles as $file) {
            static::assertStringNotContainsString('administration.json', $file);
        }
    }

    public function testLoadFetchesCoreAndPluginSnippets(): void
    {
        $loader = $this->getTranslationLoader();
        $loader->load('es-ES', $this->context);

        $writtenFiles = $this->flysystem->listContents(TranslationLoader::TRANSLATION_DIR, true)
            ->filter(fn ($item) => $item->isFile())
            ->map(fn ($item) => $item->path())
            ->toArray();

        static::assertCount(5, $writtenFiles);

        $shopwarePath = Path::join(TranslationLoader::TRANSLATION_DIR, TranslationLoader::TRANSLATION_LOCALE_SUB_DIR, 'es-ES', 'Platform');
        $shopwarePath = mb_ltrim($shopwarePath, '/\\');
        $pluginPath = Path::join(TranslationLoader::TRANSLATION_DIR, TranslationLoader::TRANSLATION_LOCALE_SUB_DIR, 'es-ES', 'Plugins', 'SwagPublisher');
        $pluginPath = mb_ltrim($pluginPath, '/\\');

        $expectedFiles = [
            $shopwarePath . '/administration.json',
            $shopwarePath . '/messages.es-ES.base.json',
            $shopwarePath . '/storefront.json',
            $pluginPath . '/storefront.json',
            $pluginPath . '/administration.json',
        ];
        sort($writtenFiles);
        sort($expectedFiles);

        static::assertSame($expectedFiles, $writtenFiles);
    }

    public function testLoadCreatesLanguageAndSnippetSet(): void
    {
        $this->languageRepository = new StaticEntityRepository([$this->getEmptySearchResult()]);
        $this->snippetSetRepository = new StaticEntityRepository([$this->getEmptySearchResult()]);

        $loader = $this->getTranslationLoader();
        $loader->load('es-ES', $this->context);

        $createdLanguages = array_shift($this->languageRepository->creates);
        static::assertIsArray($createdLanguages);
        static::assertCount(1, $createdLanguages);

        $language = array_shift($createdLanguages);
        static::assertIsArray($language);
        static::assertSame('Español', $language['name']);
        static::assertSame($this->ids->get('locale'), $language['localeId']);
        static::assertTrue($language['active']);

        $createdSnippetSets = array_shift($this->snippetSetRepository->creates);
        static::assertIsArray($createdSnippetSets);
        static::assertCount(1, $createdSnippetSets);

        $snippetSet = array_shift($createdSnippetSets);
        static::assertIsArray($snippetSet);
        static::assertSame('BASE es-ES', $snippetSet['name']);
        static::assertSame('es-ES', $snippetSet['iso']);
        static::assertSame('messages.es-ES', $snippetSet['baseFile']);
    }

    public function testTranslationDirectoryIsCreatedIfNotExists(): void
    {
        $loader = $this->getTranslationLoader();

        static::assertFalse($this->flysystem->directoryExists(TranslationLoader::TRANSLATION_DIR));
        $loader->load('es-ES', $this->context);

        $expectedDirectories = [
            'translation/locale/es-ES/Platform',
            'translation/locale/es-ES/Plugins/SwagPublisher',
        ];

        foreach ($expectedDirectories as $directory) {
            static::assertTrue($this->flysystem->directoryExists($directory));
        }
    }

    public function testGetLocalePath(): void
    {
        $loader = $this->getTranslationLoader();
        static::assertSame('', $loader->getLocalePath('_not-a-locale_'));
        static::assertSame('/translation/locale/de-DE', $loader->getLocalePath('de-DE'));
    }

    public function testPluginTranslationExists(): void
    {
        $loader = $this->getTranslationLoader();

        $noLocaleBasePathPlugin = new TestPlugin(true, '');
        $noLocaleBasePathPlugin->setName('NoLocaleBasePathExists');
        static::assertFalse($loader->pluginTranslationExists($noLocaleBasePathPlugin));

        $existingPlugin = new TestPlugin(true, '');
        $existingPlugin->setName('SwagPublisher');
        $this->flysystem->createDirectory($loader->getLocalePath('de-DE') . '/Plugins/SwagPublisher');

        static::assertTrue($loader->pluginTranslationExists($existingPlugin));
        static::assertFalse($loader->pluginTranslationExists($noLocaleBasePathPlugin));
    }

    public function testPluginTranslationExistsWorksWithMappedPlugin(): void
    {
        $pluginMapping = new PluginMappingCollection();
        $pluginMapping->add(new PluginMapping('SwagPaypal', 'MappedName'));
        $this->config = new TranslationConfig(
            new Uri('http://localhost:8000'),
            ['es-ES'],
            ['SwagPaypal'],
            new LanguageDtoCollection([new Language('es-ES', 'Español')]),
            $pluginMapping,
            new Uri('http://localhost:8000/metadata.json'),
        );
        $loader = $this->getTranslationLoader();

        $mappedNamePlugin = new TestPlugin(true, '');
        $mappedNamePlugin->setName('SwagPaypal');

        $this->flysystem->createDirectory($loader->getLocalePath('de-DE') . '/Plugins/SwagPaypal');
        static::assertFalse($loader->pluginTranslationExists($mappedNamePlugin));

        $this->flysystem->createDirectory($loader->getLocalePath('de-DE') . '/Plugins/MappedName');
        static::assertTrue($loader->pluginTranslationExists($mappedNamePlugin));
    }

    public function testLoadCreatesLanguageWithActiveFalseWhenSkipped(): void
    {
        $this->languageRepository = new StaticEntityRepository([$this->getEmptySearchResult()]);
        $this->snippetSetRepository = new StaticEntityRepository([$this->getEmptySearchResult()]);

        $loader = $this->getTranslationLoader();
        $loader->load('es-ES', $this->context, false); // activate = false

        $createdLanguages = array_shift($this->languageRepository->creates);
        static::assertIsArray($createdLanguages);
        static::assertCount(1, $createdLanguages);

        $language = array_shift($createdLanguages);
        static::assertIsArray($language);
        static::assertSame('Español', $language['name']);
        static::assertSame($this->ids->get('locale'), $language['localeId']);
        static::assertFalse($language['active']);
    }

    private function getTranslationLoader(): TranslationLoader
    {
        return new TranslationLoader(
            translationWriter: $this->flysystem,
            languageRepository: $this->languageRepository,
            localeRepository: $this->localeRepository,
            snippetSetRepository: $this->snippetSetRepository,
            client: $this->client,
            config: $this->config,
            validator: Validation::createValidator(),
        );
    }

    private function getSearchResult(string $entity): IdSearchResult
    {
        return new IdSearchResult(
            1,
            [[
                'data' => $this->ids->get($entity),
                'primaryKey' => $this->ids->get($entity),
            ]],
            new Criteria(),
            $this->context
        );
    }

    private function getEmptySearchResult(): IdSearchResult
    {
        return new IdSearchResult(
            0,
            [],
            new Criteria(),
            $this->context
        );
    }

    private function initClient(): void
    {
        $body = json_encode(['es-ES']);
        static::assertIsString($body);

        $response = new Response(200, [], $body);
        $this->client->method('request')->willReturn($response);
    }
}
