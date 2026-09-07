<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Service;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\DataTransfer\Language\Language;
use Shopware\Core\System\Snippet\DataTransfer\Language\LanguageCollection;
use Shopware\Core\System\Snippet\DataTransfer\Metadata\MetadataCollection;
use Shopware\Core\System\Snippet\DataTransfer\Metadata\MetadataEntry;
use Shopware\Core\System\Snippet\DataTransfer\PluginMapping\PluginMappingCollection;
use Shopware\Core\System\Snippet\Service\TranslationMetadataStore;
use Shopware\Core\System\Snippet\SnippetException;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(TranslationMetadataStore::class)]
class TranslationMetadataStoreTest extends TestCase
{
    private TranslationConfig $config;

    private ClientInterface&Stub $client;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter(), ['public_url' => 'http://localhost:8000']);
        $this->config = new TranslationConfig(
            new Uri('http://localhost:8000'),
            ['es-ES', 'it-IT', 'ro-RO', 'fr-FR'],
            ['SwagPublisher'],
            new LanguageCollection([
                new Language('es-ES', 'Español'),
                new Language('it-IT', 'Italiano'),
                new Language('ro-RO', 'Română'),
                new Language('fr-FR', 'Français'),
            ]),
            new PluginMappingCollection(),
            new Uri('http://localhost:8000/metadata.json'),
            ['de-DE'],
        );
    }

    public function testSaveWritesOnTheFilesystem(): void
    {
        $this->initClient([
            [
                'locale' => 'es-ES',
                'updatedAt' => '2025-08-07T11:26:28.974+00:00',
                'progress' => 100,
            ],
            [
                'locale' => 'it-IT',
                'updatedAt' => '2025-08-07T11:26:28.974+00:00',
                'progress' => 100,
            ],
        ]);

        $loader = $this->getTranslationMetadataStore();

        $metadata = $this->getMetadataCollection();
        $loader->save($metadata);

        $metadata = $this->readMetadataFromLocalFilesystem();

        static::assertArrayHasKey('es-ES', $metadata);
        static::assertArrayHasKey('it-IT', $metadata);

        $es = $metadata['es-ES'];
        static::assertSame('es-ES', $es['locale']);
        static::assertSame('2025-08-07T11:26:28.974+00:00', $es['updatedAt']);
        static::assertSame(100, $es['progress']);

        $it = $metadata['it-IT'];
        static::assertSame('it-IT', $it['locale']);
        static::assertSame('2025-08-07T11:26:28.974+00:00', $it['updatedAt']);
        static::assertSame(100, $it['progress']);
    }

    public function testThrowExceptionIfDownloadFailed(): void
    {
        $client = static::createStub(ClientInterface::class);
        $client->method('request')
            ->willThrowException(
                new ClientException(
                    'Error',
                    new Request('GET', 'localhost'),
                    new Response()
                )
            );

        $this->client = $client;
        $loader = $this->getTranslationMetadataStore();

        $this->expectExceptionObject(SnippetException::translationMetadataDownloadFailed(new Uri('http://localhost:8000/metadata.json'), new \Exception('Error')));
        $loader->getUpdatedLocalMetadata(['es-ES', 'it-IT']);
    }

    public function testGetUpdatedLocalMetadataMergesLocalAndRemoteMetadata(): void
    {
        // remote metadata
        $this->initClient([
            [
                'locale' => 'es-ES',
                'updatedAt' => '2025-08-07T11:26:28.974+00:00',
                'progress' => 100,
            ],
            [
                'locale' => 'it-IT',
                'updatedAt' => '2025-09-12T11:26:28.974+00:00',
                'progress' => 99,
            ],
            [
                'locale' => 'ro-RO',
                'updatedAt' => '2025-09-10T11:26:28.974+00:00',
                'progress' => 90,
            ],
            [
                'locale' => 'fr-FR',
                'updatedAt' => '2025-09-11T11:26:28.974+00:00',
                'progress' => 98,
            ],
        ]);

        $metadata = $this->getMetadataCollection();

        $loader = $this->getTranslationMetadataStore();
        $loader->save($metadata);

        $metadata = $this->readMetadataFromLocalFilesystem();

        // only it-IT and es-ES are stored locally
        static::assertArrayHasKey('es-ES', $metadata);
        static::assertArrayHasKey('it-IT', $metadata);
        static::assertCount(2, $metadata);

        $updated = $loader->getUpdatedLocalMetadata(['es-ES', 'it-IT', 'ro-RO']);

        // update by remote
        $it = $updated->get('it-IT');
        static::assertNotNull($it);
        static::assertSame('it-IT', $it->locale);
        static::assertSame(99, $it->progress);
        $this->assertDatetime('2025-09-12T11:26:28.974000+00:00', $it->updatedAt);

        // no update required
        $es = $updated->get('es-ES');
        static::assertNotNull($es);
        static::assertSame('es-ES', $es->locale);
        static::assertSame(100, $es->progress);
        $this->assertDatetime('2025-08-07T11:26:28.974+00:00', $es->updatedAt);

        // new locale added
        $ro = $updated->get('ro-RO');
        static::assertNotNull($ro);
        static::assertSame('ro-RO', $ro->locale);
        static::assertSame(90, $ro->progress);
        $this->assertDatetime('2025-09-10T11:26:28.974000+00:00', $ro->updatedAt);

        // no local added because not requested
        $fr = $updated->get('fr-FR');
        static::assertNull($fr);
    }

    public function testGetUpdatedLocalMetadataUpdatesAllInstalledIfNoLocalesProvided(): void
    {
        // remote metadata
        $this->initClient([
            [
                'locale' => 'it-IT',
                'updatedAt' => '2025-08-07T11:26:28.974+00:00',
                'progress' => 100,
            ],
            [
                'locale' => 'es-ES',
                'updatedAt' => '2025-08-12T11:26:28.974+00:00',
                'progress' => 100,
            ],
        ]);

        $metadata = $this->getMetadataCollection();

        $loader = $this->getTranslationMetadataStore();
        $loader->save($metadata);

        $updated = $loader->getUpdatedLocalMetadata();

        $it = $updated->get('it-IT');
        static::assertInstanceOf(MetadataEntry::class, $it);
        static::assertFalse($it->isUpdateRequired);
        $this->assertDatetime('2025-08-07T11:26:28.974+00:00', $it->updatedAt);

        $es = $updated->get('es-ES');
        static::assertInstanceOf(MetadataEntry::class, $es);
        static::assertTrue($es->isUpdateRequired);
        $this->assertDatetime('2025-08-12T11:26:28.974+00:00', $es->updatedAt);
    }

    public function testGetUpdatedLocalMetadataSkipsLocaleMissingInRemote(): void
    {
        // the remote metadata does not contain the requested locale
        $this->initClient([
            [
                'locale' => 'es-ES',
                'updatedAt' => '2025-08-07T11:26:28.974+00:00',
                'progress' => 100,
            ],
        ]);

        $updated = $this->getTranslationMetadataStore()->getUpdatedLocalMetadata(['fr-FR']);

        static::assertNull($updated->get('fr-FR'));
        static::assertSame([], $updated->getKeys());
    }

    public function testRemoveDeletesEntryFromLocalMetadata(): void
    {
        $this->initClient([]);
        $loader = $this->getTranslationMetadataStore();
        $loader->save($this->getMetadataCollection());

        $loader->remove('it-IT');

        $metadata = $this->readMetadataFromLocalFilesystem();
        static::assertArrayNotHasKey('it-IT', $metadata);
        static::assertArrayHasKey('es-ES', $metadata);
    }

    public function testRemoveIsNoOpForUnknownLocale(): void
    {
        $this->initClient([]);
        $loader = $this->getTranslationMetadataStore();
        $loader->save($this->getMetadataCollection());

        $loader->remove('fr-FR');

        $metadata = $this->readMetadataFromLocalFilesystem();
        static::assertArrayHasKey('it-IT', $metadata);
        static::assertArrayHasKey('es-ES', $metadata);
    }

    public function testGetTranslationListMergesInstalledAndRemoteState(): void
    {
        // remote offers a newer it-IT, an unchanged es-ES and a ro-RO that is not installed locally
        $this->initClient([
            ['locale' => 'it-IT', 'updatedAt' => '2025-09-12T11:26:28.974+00:00', 'progress' => 99],
            ['locale' => 'es-ES', 'updatedAt' => '2025-08-07T11:26:28.974+00:00', 'progress' => 100],
            ['locale' => 'ro-RO', 'updatedAt' => '2025-09-10T11:26:28.974+00:00', 'progress' => 90],
        ]);

        $store = $this->getTranslationMetadataStore();
        $store->save($this->getMetadataCollection());

        $byLocale = array_column($store->getTranslationList(), null, 'locale');

        // it-IT: installed at an older version than the remote => update available, local install marker stays
        static::assertSame(99, $byLocale['it-IT']['progress']);
        static::assertNotNull($byLocale['it-IT']['lastUpdate']);
        static::assertTrue($byLocale['it-IT']['updateAvailable']);

        // es-ES: installed and remote share the same version => no update available
        static::assertSame(100, $byLocale['es-ES']['progress']);
        static::assertNotNull($byLocale['es-ES']['lastUpdate']);
        static::assertFalse($byLocale['es-ES']['updateAvailable']);

        // ro-RO: remote progress is reported even though it is not installed
        static::assertSame(90, $byLocale['ro-RO']['progress']);
        static::assertNull($byLocale['ro-RO']['lastUpdate']);
        static::assertFalse($byLocale['ro-RO']['updateAvailable']);

        static::assertFalse($byLocale['it-IT']['isPseudoLanguage']);
    }

    public function testGetTranslationListDegradesWhenRemoteMetadataUnavailable(): void
    {
        $client = static::createStub(ClientInterface::class);
        $client->method('request')->willThrowException(
            new ClientException('Error', new Request('GET', 'localhost'), new Response())
        );
        $this->client = $client;

        $store = $this->getTranslationMetadataStore();
        $store->save($this->getMetadataCollection());

        $byLocale = array_column($store->getTranslationList(), null, 'locale');

        // remote down => no progress, no update flag; local install marker (lastUpdate) stays
        static::assertNull($byLocale['it-IT']['progress']);
        static::assertFalse($byLocale['it-IT']['updateAvailable']);
        static::assertNotNull($byLocale['it-IT']['lastUpdate']);
    }

    public function testGetTranslationListFlagsPseudoLanguages(): void
    {
        $this->config = new TranslationConfig(
            new Uri('http://localhost:8000'),
            ['fr-FR', 'ach-UG'],
            [],
            new LanguageCollection([
                new Language('fr-FR', 'Français'),
                new Language('ach-UG', 'Acholi (Pseudo Language)'),
            ]),
            new PluginMappingCollection(),
            new Uri('http://localhost:8000/metadata.json'),
            ['de-DE'],
            null,
            null,
            ['ach-UG'],
        );
        $this->initClient([]);

        $byLocale = array_column($this->getTranslationMetadataStore()->getTranslationList(), null, 'locale');

        static::assertTrue($byLocale['ach-UG']['isPseudoLanguage']);
        static::assertFalse($byLocale['fr-FR']['isPseudoLanguage']);
    }

    public function testTranslationListCachesRemoteMetadataAcrossCalls(): void
    {
        $response = new Response(body: (string) json_encode([
            ['locale' => 'es-ES', 'updatedAt' => '2025-08-07T11:26:28.974+00:00', 'progress' => 100],
        ], \JSON_THROW_ON_ERROR));

        $client = $this->createMock(ClientInterface::class);
        // The list path fetches the remote metadata once and serves the second call from the cache.
        $client->expects($this->once())->method('request')->willReturn($response);

        $store = new TranslationMetadataStore($this->config, $client, $this->filesystem, new ArrayAdapter());

        $store->getTranslationList();
        $store->getTranslationList();
    }

    private function getTranslationMetadataStore(): TranslationMetadataStore
    {
        return new TranslationMetadataStore($this->config, $this->client, $this->filesystem, new ArrayAdapter());
    }

    /**
     * @param array<array{locale: string, updatedAt: string, progress: int}> $items
     */
    private function initClient(array $items): void
    {
        $response = new Response(body: json_encode($items, \JSON_THROW_ON_ERROR));

        $client = static::createStub(ClientInterface::class);
        $client->method('request')->willReturn($response);

        $this->client = $client;
    }

    private function getMetadataCollection(): MetadataCollection
    {
        $elements = [
            MetadataEntry::create([
                'locale' => 'it-IT',
                'updatedAt' => '2025-08-07T11:26:28.974+00:00',
                'progress' => 100,
            ]),
            MetadataEntry::create([
                'locale' => 'es-ES',
                'updatedAt' => '2025-08-07T11:26:28.974+00:00',
                'progress' => 100,
            ]),
        ];

        return new MetadataCollection($elements);
    }

    private function assertDatetime(string $expectedDateTimeString, \DateTime $actualDateTime): void
    {
        static::assertSame(
            (new \DateTime($expectedDateTimeString))->getTimestamp(),
            $actualDateTime->getTimestamp()
        );
    }

    /**
     * @return array{locale: string, updatedAt: string, progress: int}[]
     */
    private function readMetadataFromLocalFilesystem(): array
    {
        $metadata = $this->filesystem->read('translation/crowdin-metadata.lock');
        $metadata = json_decode($metadata, true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($metadata);

        return $metadata;
    }
}
