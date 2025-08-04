<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Service;

require_once __DIR__ . '/../Mock/FilePutContentsMock.php';

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
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
use Shopware\Core\System\Snippet\Service\FilePutContentsMock;
use Shopware\Core\System\Snippet\Service\TranslationLoader;
use Shopware\Core\System\Snippet\SnippetException;
use Shopware\Core\System\Snippet\Struct\Language;
use Shopware\Core\System\Snippet\Struct\LanguageCollection as LanguageDtoCollection;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(TranslationLoader::class)]
class TranslationLoaderTest extends TestCase
{
    private ClientInterface&MockObject $client;

    private Filesystem&MockObject $filesystem;

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
        $this->filesystem = $this->createMock(Filesystem::class);
        $this->context = Context::createDefaultContext();
        $this->ids = new IdsCollection();
        $this->languageRepository = new StaticEntityRepository([$this->getSearchResult('language')]);
        $this->localeRepository = new StaticEntityRepository([$this->getSearchResult('locale')]);
        $this->snippetSetRepository = new StaticEntityRepository([$this->getSearchResult('snippet-set')]);
        $this->config = new TranslationConfig(
            'https://example.com',
            ['es-ES'],
            ['SwagPublisher'],
            new LanguageDtoCollection([new Language('es-ES', 'Español')]),
            []
        );
        $this->initClient();
    }

    protected function tearDown(): void
    {
        FilePutContentsMock::reset();
    }

    public function testLoadThrowsExceptionIfLanguageDoesNotExist(): void
    {
        $loader = $this->getTranslationLoader();

        static::expectException(SnippetException::class);
        $loader->load('non-existent-language', $this->context);
    }

    public function testThrowExceptionIfProvidedLocaleDoesNotExist(): void
    {
        $this->localeRepository = new StaticEntityRepository([$this->getEmptySearchResult()]);

        $loader = $this->getTranslationLoader();

        static::expectException(SnippetException::class);
        $loader->load('es-ES', $this->context);
    }

    public function testLoadFetchesCoreAndPluginSnippets(): void
    {
        $loader = $this->getTranslationLoader();
        $loader->load('es-ES', $this->context);

        $fileNames = FilePutContentsMock::$fileNames;
        static::assertCount(5, $fileNames);

        $shopwarePath = realpath(TranslationLoader::TRANSLATION_DESTINATION) . '/es-ES/Platform/';
        $pluginPath = realpath(TranslationLoader::TRANSLATION_DESTINATION) . '/es-ES/Plugins/SwagPublisher/';

        static::assertEquals([
            $shopwarePath . 'administration.json',
            $shopwarePath . 'messages.es-ES.base.json',
            $shopwarePath . 'storefront.json',
            $pluginPath . 'storefront.json',
            $pluginPath . 'administration.json',
        ], $fileNames);
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
        $path = realpath(TranslationLoader::TRANSLATION_DESTINATION);
        $expectedPaths = [
            $path . '/es-ES/Platform/',
            $path . '/es-ES/Plugins/SwagPublisher/',
        ];

        $this->filesystem->method('exists')->willReturn(false);
        $this->filesystem->method('mkdir')->willReturnCallback(function (string $path) use ($expectedPaths): void {
            static::assertTrue(\in_array($path, $expectedPaths, true));
        });

        $loader = $this->getTranslationLoader();
        $loader->load('es-ES', $this->context);
    }

    private function getTranslationLoader(): TranslationLoader
    {
        return new TranslationLoader(
            filesystem: $this->filesystem,
            languageRepository: $this->languageRepository,
            localeRepository: $this->localeRepository,
            snippetSetRepository: $this->snippetSetRepository,
            client: $this->client,
            config: $this->config,
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
