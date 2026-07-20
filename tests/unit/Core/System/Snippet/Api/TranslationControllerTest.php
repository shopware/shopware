<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Api;

use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\Attributes\CoversClass;
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
use Shopware\Core\System\Snippet\Service\TranslationUpdater;
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

    protected function setUp(): void
    {
        $this->config = new TranslationConfig(
            new Uri('http://localhost:8000'),
            ['fr-FR', 'es-ES', 'ach-UG'],
            [],
            new LanguageCollection([
                new Language('fr-FR', 'Français'),
                new Language('es-ES', 'Español'),
                new Language('ach-UG', 'Acholi (Pseudo Language)'),
            ]),
            new PluginMappingCollection(),
            new Uri('http://localhost:8000/metadata.json'),
            ['de-DE', 'en-GB'],
            new Uri('https://translate.shopware.com'),
            new Uri('https://developer.shopware.com/docs/concepts/translations/'),
            ['ach-UG'],
            90,
        );
    }

    public function testListReturnsRemoteProgressAndUpdateState(): void
    {
        $metadataStore = static::createStub(TranslationMetadataStore::class);
        // fr-FR is installed at an older version than the remote offers
        $metadataStore->method('getLocalMetadata')->willReturn(new MetadataCollection([
            MetadataEntry::create([
                'locale' => 'fr-FR',
                'updatedAt' => '2025-08-07T11:26:28.974+00:00',
                'progress' => 90,
            ]),
        ]));
        $metadataStore->method('getRemoteMetadata')->willReturn(new MetadataCollection([
            MetadataEntry::create([
                'locale' => 'fr-FR',
                'updatedAt' => '2026-01-01T00:00:00.000+00:00',
                'progress' => 100,
            ]),
            MetadataEntry::create([
                'locale' => 'es-ES',
                'updatedAt' => '2026-01-01T00:00:00.000+00:00',
                'progress' => 80,
            ]),
        ]));

        $content = $this->decode($this->createController(metadataStore: $metadataStore)->list());
        static::assertSame(3, $content['total']);
        static::assertSame('https://translate.shopware.com', $content['meta']['communityTranslationsUrl']);
        static::assertSame('https://developer.shopware.com/docs/concepts/translations/', $content['meta']['documentationUrl']);
        static::assertSame(90, $content['meta']['completenessThreshold']);
        static::assertSame(['de-DE', 'en-GB'], $content['meta']['builtInLocales']);

        $byLocale = array_column($content['items'], null, 'locale');

        // fr-FR: progress comes from remote, installed => lastUpdate set, remote newer => updateAvailable
        static::assertSame(100, $byLocale['fr-FR']['progress']);
        static::assertNotNull($byLocale['fr-FR']['lastUpdate']);
        static::assertTrue($byLocale['fr-FR']['updateAvailable']);

        // es-ES: remote progress is reported even though it is not installed
        static::assertSame(80, $byLocale['es-ES']['progress']);
        static::assertNull($byLocale['es-ES']['lastUpdate']);
        static::assertFalse($byLocale['es-ES']['updateAvailable']);

        // ach-UG is configured as a pseudo language, the real locales are not
        static::assertTrue($byLocale['ach-UG']['isPseudoLanguage']);
        static::assertFalse($byLocale['fr-FR']['isPseudoLanguage']);
        static::assertFalse($byLocale['es-ES']['isPseudoLanguage']);
    }

    public function testListReportsRemoteProgressWhenNothingInstalled(): void
    {
        $metadataStore = static::createStub(TranslationMetadataStore::class);
        $metadataStore->method('getLocalMetadata')->willReturn(new MetadataCollection());
        $metadataStore->method('getRemoteMetadata')->willReturn(new MetadataCollection([
            MetadataEntry::create([
                'locale' => 'fr-FR',
                'updatedAt' => '2026-01-01T00:00:00.000+00:00',
                'progress' => 100,
            ]),
        ]));

        $content = $this->decode($this->createController(metadataStore: $metadataStore)->list());
        $byLocale = array_column($content['items'], null, 'locale');

        static::assertSame(100, $byLocale['fr-FR']['progress']);
        static::assertNull($byLocale['fr-FR']['lastUpdate']);
        static::assertFalse($byLocale['fr-FR']['updateAvailable']);
    }

    public function testListDegradesWhenRemoteMetadataUnavailable(): void
    {
        $metadataStore = static::createStub(TranslationMetadataStore::class);
        $metadataStore->method('getLocalMetadata')->willReturn(new MetadataCollection([
            MetadataEntry::create([
                'locale' => 'fr-FR',
                'updatedAt' => '2025-08-07T11:26:28.974+00:00',
                'progress' => 90,
            ]),
        ]));
        $metadataStore->method('getRemoteMetadata')->willThrowException(new \RuntimeException('remote unavailable'));

        $content = $this->decode($this->createController(metadataStore: $metadataStore)->list());
        $byLocale = array_column($content['items'], null, 'locale');

        // remote down => no progress, no update flag; local install marker (lastUpdate) stays
        static::assertNull($byLocale['fr-FR']['progress']);
        static::assertFalse($byLocale['fr-FR']['updateAvailable']);
        static::assertNotNull($byLocale['fr-FR']['lastUpdate']);
    }

    public function testInstallLoadsRequestedLocalesAndSavesMetadata(): void
    {
        $metadata = $this->metadataCollection(['fr-FR' => true, 'es-ES' => false]);
        $metadataStore = $this->createMock(TranslationMetadataStore::class);
        $metadataStore->expects($this->once())
            ->method('getUpdatedLocalMetadata')
            ->with(['fr-FR', 'es-ES'])
            ->willReturn($metadata);
        $metadataStore->expects($this->once())->method('save')->with($metadata);

        $translationLoader = $this->createMock(AbstractTranslationLoader::class);
        $translationLoader->expects($this->once())
            ->method('load')
            ->with('fr-FR', static::isInstanceOf(Context::class), true);

        $response = $this->createController($metadataStore, $translationLoader)->install(
            new InstallTranslationRequest(locales: ['fr-FR', 'es-ES']),
            $this->context()
        );

        $content = $this->decode($response);
        static::assertSame(['fr-FR'], $content['updated']);
        static::assertSame(['es-ES'], $content['skipped']);
        static::assertSame([], $content['unavailable']);
    }

    public function testInstallReportsRequestedLocalesWithoutTranslation(): void
    {
        // The remote metadata has no entry for the requested locale, so nothing is installed for it.
        $metadataStore = static::createStub(TranslationMetadataStore::class);
        $metadataStore->method('getUpdatedLocalMetadata')->willReturn(new MetadataCollection());

        $translationLoader = $this->createMock(AbstractTranslationLoader::class);
        $translationLoader->expects($this->never())->method('load');

        $response = $this->createController($metadataStore, $translationLoader)->install(
            new InstallTranslationRequest(locales: ['fr-FR']),
            $this->context()
        );

        $content = $this->decode($response);
        static::assertSame([], $content['updated']);
        static::assertSame([], $content['skipped']);
        static::assertSame(['fr-FR'], $content['unavailable']);
    }

    public function testInstallAllUsesConfiguredLocales(): void
    {
        $metadataStore = $this->createMock(TranslationMetadataStore::class);
        $metadataStore->expects($this->once())
            ->method('getUpdatedLocalMetadata')
            ->with(['fr-FR', 'es-ES', 'ach-UG'])
            ->willReturn($this->metadataCollection(['fr-FR' => false, 'es-ES' => false]));

        $this->createController($metadataStore)->install(new InstallTranslationRequest(all: true), $this->context());
    }

    public function testInstallActivateFalseIsPassedToLoader(): void
    {
        $metadataStore = static::createStub(TranslationMetadataStore::class);
        $metadataStore->method('getUpdatedLocalMetadata')->willReturn($this->metadataCollection(['fr-FR' => true]));

        $translationLoader = $this->createMock(AbstractTranslationLoader::class);
        $translationLoader->expects($this->once())
            ->method('load')
            ->with('fr-FR', static::isInstanceOf(Context::class), false);

        $this->createController($metadataStore, $translationLoader)->install(
            new InstallTranslationRequest(locales: ['fr-FR'], activate: false),
            $this->context()
        );
    }

    public function testInstallThrowsOnInvalidLocale(): void
    {
        $metadataStore = $this->createMock(TranslationMetadataStore::class);
        $metadataStore->expects($this->never())->method('getUpdatedLocalMetadata');

        $translationLoader = $this->createMock(AbstractTranslationLoader::class);
        $translationLoader->expects($this->never())->method('load');

        $this->expectExceptionObject(SnippetException::invalidLocalesProvided('xx-XX', 'fr-FR, es-ES, ach-UG'));

        $this->createController($metadataStore, $translationLoader)->install(
            new InstallTranslationRequest(locales: ['xx-XX']),
            $this->context()
        );
    }

    public function testInstallThrowsWhenNoLocalesProvided(): void
    {
        $translationLoader = $this->createMock(AbstractTranslationLoader::class);
        $translationLoader->expects($this->never())->method('load');

        $this->expectExceptionObject(SnippetException::noLocalesArgumentProvided());

        $this->createController(translationLoader: $translationLoader)->install(
            new InstallTranslationRequest(),
            $this->context()
        );
    }

    public function testUpdateLoadsAllInstalledRequiringUpdate(): void
    {
        $metadata = $this->metadataCollection(['fr-FR' => true, 'es-ES' => false]);
        $metadataStore = $this->createMock(TranslationMetadataStore::class);
        $metadataStore->method('getLocalMetadata')->willReturn($metadata);
        $metadataStore->expects($this->once())
            ->method('getUpdatedLocalMetadata')
            ->with(null)
            ->willReturn($metadata);
        $metadataStore->expects($this->once())->method('save')->with($metadata);

        $translationLoader = $this->createMock(AbstractTranslationLoader::class);
        $translationLoader->expects($this->once())
            ->method('load')
            ->with('fr-FR', static::isInstanceOf(Context::class), true);

        $response = $this->createController($metadataStore, $translationLoader)->update($this->context());

        $content = $this->decode($response);
        static::assertSame(['fr-FR'], $content['updated']);
        static::assertSame(['es-ES'], $content['skipped']);
        static::assertSame([], $content['unavailable']);
    }

    public function testDeleteRemovesFilesAndMetadata(): void
    {
        $translationRemover = $this->createMock(TranslationRemover::class);
        $translationRemover->expects($this->once())->method('remove')->with('fr-FR');

        $response = $this->createController(translationRemover: $translationRemover)->delete('fr-FR');

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertEmpty($response->getContent());
    }

    public function testDeleteThrowsOnInvalidLocale(): void
    {
        $translationRemover = $this->createMock(TranslationRemover::class);
        $translationRemover->expects($this->never())->method('remove');

        $this->expectExceptionObject(SnippetException::invalidLocalesProvided('xx-XX', 'fr-FR, es-ES, ach-UG'));

        $this->createController(translationRemover: $translationRemover)->delete('xx-XX');
    }

    private function createController(
        ?TranslationMetadataStore $metadataStore = null,
        ?AbstractTranslationLoader $translationLoader = null,
        ?TranslationRemover $translationRemover = null,
    ): TranslationController {
        $metadataStore ??= static::createStub(TranslationMetadataStore::class);
        $translationLoader ??= static::createStub(AbstractTranslationLoader::class);

        return new TranslationController(
            $this->config,
            $metadataStore,
            new TranslationUpdater($translationLoader, $metadataStore),
            $translationRemover ?? static::createStub(TranslationRemover::class),
        );
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
