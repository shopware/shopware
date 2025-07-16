<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Service;

require_once __DIR__ . '/../Mock/FilePutContentsMock.php';

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetEntity;
use Shopware\Core\System\Snippet\Service\FilePutContentsMock;
use Shopware\Core\System\Snippet\Service\TranslationLoader;
use Shopware\Core\System\Snippet\SnippetException;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(TranslationLoader::class)]
class TranslationLoaderTest extends TestCase
{
    private Client&MockObject $client;

    protected function setUp(): void
    {
        $this->client = $this->createMock(Client::class);

        $response = new Response(200, [], json_encode([
            'es-ES',
        ]));

        $this->client->method('request')->willReturn($response);
    }

    public function testLoadThrowsExceptionIfLanguageDoesNotExist(): void
    {
        $loader = $this->getTranslationLoader();

        static::expectException(SnippetException::class);
        $loader->load('non-existent-language', Context::createDefaultContext());
    }

    public function testLoadTranslation(): void
    {
        $loader = $this->getTranslationLoader();
        $loader->load('es-ES', Context::createDefaultContext());

        $calls = FilePutContentsMock::$calls;

        // todo: require the configuration loader in DI and mock the configuration to not work with the real config
        static::assertNotEmpty($calls);
    }

    private function getTranslationLoader(): TranslationLoader
    {
        $localeId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $snippetSetId = Uuid::randomHex();

        $language = new LanguageEntity();
        $language->setId(Uuid::randomHex());

        $locale = new LocaleEntity();
        $locale->setId(Uuid::randomHex());

        $snippetSet = new SnippetSetEntity();
        $snippetSet->setId(Uuid::randomHex());

        $context = Context::createDefaultContext();
        $criteria = new Criteria();

        return new TranslationLoader(
            filesystem: $this->createMock(Filesystem::class),
            languageRepository: new StaticEntityRepository([
                new IdSearchResult(
                    1,
                    [['data' => $languageId, 'primaryKey' => $languageId]],
                    $criteria,
                    $context
                )]),
            localeRepository: new StaticEntityRepository([
                new IdSearchResult(
                    1,
                    [['data' => $localeId, 'primaryKey' => $localeId]],
                    $criteria,
                    $context
                )]),
            snippetSetRepository: new StaticEntityRepository([
                new IdSearchResult(
                    1,
                    [['data' => $snippetSetId, 'primaryKey' => $snippetSetId]],
                    $criteria,
                    $context
                )]),
            client: $this->client,
        );
    }
}
