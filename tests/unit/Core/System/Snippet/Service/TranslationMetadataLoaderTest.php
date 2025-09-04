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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\DataTransfer\Language\Language;
use Shopware\Core\System\Snippet\DataTransfer\Language\LanguageCollection;
use Shopware\Core\System\Snippet\DataTransfer\Metadata\MetadataCollection;
use Shopware\Core\System\Snippet\DataTransfer\Metadata\MetadataEntry;
use Shopware\Core\System\Snippet\DataTransfer\PluginMapping\PluginMappingCollection;
use Shopware\Core\System\Snippet\Service\TranslationMetadataLoader;
use Shopware\Core\System\Snippet\SnippetException;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(TranslationMetadataLoader::class)]
class TranslationMetadataLoaderTest extends TestCase
{
    private TranslationConfig $config;

    private ClientInterface&MockObject $client;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter(), ['public_url' => 'http://localhost:8000']);
        $this->config = new TranslationConfig(
            new Uri('http://localhost:8000'),
            ['es-ES', 'en-GB', 'de-DE', 'fr-FR'],
            ['SwagPublisher'],
            new LanguageCollection([
                new Language('es-ES', 'Español'),
                new Language('en-GB', 'English'),
                new Language('de-DE', 'Deutsch'),
                new Language('fr-FR', 'Français'),
            ]),
            new PluginMappingCollection(),
            new Uri('http://localhost:8000/metadata.json'),
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
                'locale' => 'en-GB',
                'updatedAt' => '2025-08-07T11:26:28.974+00:00',
                'progress' => 100,
            ],
        ]);

        $loader = $this->getTranslationMetadataLoader();

        $metadata = $this->getMetadataCollection();
        $loader->save($metadata);

        $metadata = $this->readMetadataFromLocalFilesystem();

        static::assertArrayHasKey('es-ES', $metadata);
        static::assertArrayHasKey('en-GB', $metadata);

        $es = $metadata['es-ES'];
        static::assertSame('es-ES', $es['locale']);
        static::assertSame('2025-08-07T11:26:28.974+00:00', $es['updatedAt']);
        static::assertSame(100, $es['progress']);

        $en = $metadata['en-GB'];
        static::assertSame('en-GB', $en['locale']);
        static::assertSame('2025-08-07T11:26:28.974+00:00', $en['updatedAt']);
        static::assertSame(100, $en['progress']);
    }

    public function testThrowExceptionIfDownloadFailed(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->method('request')
            ->willThrowException(
                new ClientException(
                    'Error',
                    new Request('GET', 'localhost'),
                    new Response()
                )
            );

        $this->client = $client;
        $loader = $this->getTranslationMetadataLoader();

        static::expectException(SnippetException::class);
        static::expectExceptionMessage('Failed to download translation metadata from "http://localhost:8000/metadata.json": Error');
        $loader->getUpdatedMetadata(['es-ES', 'en-GB']);
    }

    public function testGetUpdatedMetadataMergesLocalAndRemoteMetadata(): void
    {
        // remote metadata
        $this->initClient([
            [
                'locale' => 'es-ES',
                'updatedAt' => '2025-08-07T11:26:28.974+00:00',
                'progress' => 100,
            ],
            [
                'locale' => 'en-GB',
                'updatedAt' => '2025-09-12T11:26:28.974+00:00',
                'progress' => 99,
            ],
            [
                'locale' => 'de-DE',
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

        $loader = $this->getTranslationMetadataLoader();
        $loader->save($metadata);

        $metadata = $this->readMetadataFromLocalFilesystem();

        // only en-GB and es-ES are stored locally
        static::assertArrayHasKey('es-ES', $metadata);
        static::assertArrayHasKey('en-GB', $metadata);
        static::assertCount(2, $metadata);

        $updated = $loader->getUpdatedMetadata(['es-ES', 'en-GB', 'de-DE']);

        // update by remote
        $gb = $updated->get('en-GB');
        static::assertNotNull($gb);
        static::assertSame('en-GB', $gb->locale);
        static::assertSame(99, $gb->progress);
        $this->assertDatetime('2025-09-12T11:26:28.974000+00:00', $gb->updatedAt);

        // no update required
        $gb = $updated->get('es-ES');
        static::assertNotNull($gb);
        static::assertSame('es-ES', $gb->locale);
        static::assertSame(100, $gb->progress);
        $this->assertDatetime('2025-08-07T11:26:28.974+00:00', $gb->updatedAt);

        // new locale added
        $de = $updated->get('de-DE');
        static::assertNotNull($de);
        static::assertSame('de-DE', $de->locale);
        static::assertSame(90, $de->progress);
        $this->assertDatetime('2025-09-10T11:26:28.974000+00:00', $de->updatedAt);

        // no local added because not requested
        $fr = $updated->get('fr-FR');
        static::assertNull($fr);
    }

    private function getTranslationMetadataLoader(): TranslationMetadataLoader
    {
        return new TranslationMetadataLoader($this->config, $this->client, $this->filesystem);
    }

    /**
     * @param array<array{locale: string, updatedAt: string, progress: int}> $items
     */
    private function initClient(array $items): void
    {
        $response = new Response(body: json_encode($items, \JSON_THROW_ON_ERROR));

        $client = $this->createMock(ClientInterface::class);
        $client->method('request')->willReturn($response);

        $this->client = $client;
    }

    private function getMetadataCollection(): MetadataCollection
    {
        $elements = [
            MetadataEntry::create([
                'locale' => 'en-GB',
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
