<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Api;

use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Api\TranslationController;
use Shopware\Core\System\Snippet\DataTransfer\Language\Language;
use Shopware\Core\System\Snippet\DataTransfer\Language\LanguageCollection;
use Shopware\Core\System\Snippet\DataTransfer\Metadata\MetadataCollection;
use Shopware\Core\System\Snippet\DataTransfer\Metadata\MetadataEntry;
use Shopware\Core\System\Snippet\DataTransfer\PluginMapping\PluginMappingCollection;
use Shopware\Core\System\Snippet\Request\InstallTranslationRequest;
use Shopware\Core\System\Snippet\Service\AbstractTranslationLoader;
use Shopware\Core\System\Snippet\Service\TranslationMetadataStore;
use Shopware\Core\System\Snippet\Service\TranslationRemover;
use Shopware\Core\System\Snippet\SnippetException;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(TranslationController::class)]
class TranslationControllerTest extends TestCase
{
    private TranslationConfig $config;

    private TranslationMetadataStore&MockObject $metadataStore;

    private AbstractTranslationLoader&MockObject $translationLoader;

    private TranslationRemover&MockObject $translationRemover;

    private TranslationController $controller;

    protected function setUp(): void
    {
        $this->config = new TranslationConfig(
            new Uri('http://localhost:8000'),
            ['de-DE', 'es-ES'],
            [],
            new LanguageCollection([
                new Language('de-DE', 'Deutsch'),
                new Language('es-ES', 'Español'),
            ]),
            new PluginMappingCollection(),
            new Uri('http://localhost:8000/metadata.json'),
            [],
        );
        $this->metadataStore = $this->createMock(TranslationMetadataStore::class);
        $this->translationLoader = $this->createMock(AbstractTranslationLoader::class);
        $this->translationRemover = $this->createMock(TranslationRemover::class);

        $this->controller = new TranslationController(
            $this->config,
            $this->metadataStore,
            $this->translationLoader,
            $this->translationRemover,
        );
    }

    public function testListReturnsConfiguredLocalesWithMetadata(): void
    {
        $this->metadataStore->method('getLocalMetadata')->willReturn(new MetadataCollection([
            MetadataEntry::create([
                'locale' => 'de-DE',
                'updatedAt' => '2025-08-07T11:26:28.974+00:00',
                'progress' => 100,
            ]),
        ]));

        $response = $this->controller->list();

        $content = $this->decode($response);
        static::assertSame(2, $content['total']);
        static::assertCount(2, $content['items']);

        $byLocale = array_column($content['items'], null, 'locale');

        static::assertSame('Deutsch', $byLocale['de-DE']['name']);
        static::assertSame(100, $byLocale['de-DE']['progress']);
        static::assertNotNull($byLocale['de-DE']['lastUpdate']);

        // es-ES is configured but not installed
        static::assertSame('Español', $byLocale['es-ES']['name']);
        static::assertNull($byLocale['es-ES']['progress']);
        static::assertNull($byLocale['es-ES']['lastUpdate']);
    }

    public function testInstallLoadsRequestedLocalesAndSavesMetadata(): void
    {
        $metadata = $this->metadataCollection(['de-DE' => true, 'es-ES' => false]);
        $this->metadataStore->expects($this->once())
            ->method('getUpdatedLocalMetadata')
            ->with(['de-DE', 'es-ES'])
            ->willReturn($metadata);

        $this->translationLoader->expects($this->once())
            ->method('load')
            ->with('de-DE', static::isInstanceOf(Context::class), true);

        $this->metadataStore->expects($this->once())->method('save')->with($metadata);

        $response = $this->controller->install(
            new InstallTranslationRequest(locales: ['de-DE', 'es-ES']),
            $this->context()
        );

        $content = $this->decode($response);
        static::assertSame(['de-DE'], $content['updated']);
        static::assertSame(['es-ES'], $content['skipped']);
        static::assertSame([], $content['unavailable']);
    }

    public function testInstallReportsRequestedLocalesWithoutTranslation(): void
    {
        // The remote metadata has no entry for the requested locale, so nothing is installed for it.
        $this->metadataStore->method('getUpdatedLocalMetadata')->willReturn(new MetadataCollection());
        $this->translationLoader->expects($this->never())->method('load');

        $response = $this->controller->install(new InstallTranslationRequest(locales: ['de-DE']), $this->context());

        $content = $this->decode($response);
        static::assertSame([], $content['updated']);
        static::assertSame([], $content['skipped']);
        static::assertSame(['de-DE'], $content['unavailable']);
    }

    public function testInstallAllUsesConfiguredLocales(): void
    {
        $this->metadataStore->expects($this->once())
            ->method('getUpdatedLocalMetadata')
            ->with(['de-DE', 'es-ES'])
            ->willReturn($this->metadataCollection(['de-DE' => false, 'es-ES' => false]));

        $this->controller->install(new InstallTranslationRequest(all: true), $this->context());
    }

    public function testInstallActivateFalseIsPassedToLoader(): void
    {
        $this->metadataStore->method('getUpdatedLocalMetadata')
            ->willReturn($this->metadataCollection(['de-DE' => true]));

        $this->translationLoader->expects($this->once())
            ->method('load')
            ->with('de-DE', static::isInstanceOf(Context::class), false);

        $this->controller->install(
            new InstallTranslationRequest(locales: ['de-DE'], activate: false),
            $this->context()
        );
    }

    public function testInstallThrowsOnInvalidLocale(): void
    {
        $this->translationLoader->expects($this->never())->method('load');
        $this->metadataStore->expects($this->never())->method('getUpdatedLocalMetadata');

        $this->expectExceptionObject(SnippetException::invalidLocalesProvided('xx-XX', 'de-DE, es-ES'));

        $this->controller->install(new InstallTranslationRequest(locales: ['xx-XX']), $this->context());
    }

    public function testInstallThrowsWhenNoLocalesProvided(): void
    {
        $this->translationLoader->expects($this->never())->method('load');

        $this->expectExceptionObject(SnippetException::noLocalesArgumentProvided());

        $this->controller->install(new InstallTranslationRequest(), $this->context());
    }

    public function testUpdateLoadsAllInstalledRequiringUpdate(): void
    {
        $metadata = $this->metadataCollection(['de-DE' => true, 'es-ES' => false]);
        $this->metadataStore->expects($this->once())
            ->method('getUpdatedLocalMetadata')
            ->with(null)
            ->willReturn($metadata);

        $this->translationLoader->expects($this->once())
            ->method('load')
            ->with('de-DE', static::isInstanceOf(Context::class), true);

        $this->metadataStore->expects($this->once())->method('save')->with($metadata);

        $response = $this->controller->update($this->context());

        $content = $this->decode($response);
        static::assertSame(['de-DE'], $content['updated']);
        static::assertSame(['es-ES'], $content['skipped']);
        static::assertSame([], $content['unavailable']);
    }

    public function testDeleteRemovesFilesAndMetadata(): void
    {
        $this->translationRemover->expects($this->once())->method('remove')->with('de-DE');

        $response = $this->controller->delete('de-DE');

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertEmpty($response->getContent());
    }

    public function testDeleteThrowsOnInvalidLocale(): void
    {
        $this->translationRemover->expects($this->never())->method('remove');

        $this->expectExceptionObject(SnippetException::invalidLocalesProvided('xx-XX', 'de-DE, es-ES'));

        $this->controller->delete('xx-XX');
    }

    /**
     * @param array<string, bool> $localesRequiringUpdate keyed by locale, value = isUpdateRequired
     */
    private function metadataCollection(array $localesRequiringUpdate): MetadataCollection
    {
        $collection = new MetadataCollection();
        foreach ($localesRequiringUpdate as $locale => $requiresUpdate) {
            $entry = MetadataEntry::create([
                'locale' => $locale,
                'updatedAt' => '2025-08-07T11:26:28.974+00:00',
                'progress' => 100,
            ]);

            if ($requiresUpdate) {
                $entry->markForUpdate();
            }

            $collection->add($entry);
        }

        return $collection;
    }

    private function context(): Context
    {
        return new Context(new SystemSource());
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        $content = $response->getContent();
        static::assertIsString($content);

        $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($decoded);

        return $decoded;
    }
}
