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
            'sw-settings-language.addModal.docsUrl',
            ['ach-UG'],
            90,
        );
    }

    public function testListWrapsTheItemsBuiltByTheMetadataStore(): void
    {
        $items = [
            ['locale' => 'fr-FR', 'name' => 'Français', 'lastUpdate' => null, 'progress' => 100, 'updateAvailable' => false, 'isPseudoLanguage' => false],
            ['locale' => 'ach-UG', 'name' => 'Acholi (Pseudo Language)', 'lastUpdate' => null, 'progress' => 42, 'updateAvailable' => true, 'isPseudoLanguage' => true],
        ];

        $metadataStore = static::createStub(TranslationMetadataStore::class);
        $metadataStore->method('getTranslationList')->willReturn($items);

        $content = $this->decode($this->createController(metadataStore: $metadataStore)->list());

        static::assertSame(2, $content['total']);
        static::assertSame($items, $content['items']);
        // meta moved to a dedicated endpoint and must no longer be part of the list response
        static::assertArrayNotHasKey('meta', $content);
    }

    public function testMetaReturnsTheConfiguredMetaInformation(): void
    {
        $content = $this->decode($this->createController()->meta());

        static::assertSame(['de-DE', 'en-GB'], $content['builtInLocales']);
        static::assertSame('https://translate.shopware.com', $content['communityTranslationsUrl']);
        static::assertSame('sw-settings-language.addModal.docsUrl', $content['documentationUrlSnippetKey']);
        static::assertSame(90, $content['completenessThreshold']);
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
        $translationLoader->method('hasTranslationFiles')->willReturn(true);
        $translationLoader->expects($this->once())->method('download')->with('fr-FR');

        $linked = [];
        $translationLoader->expects($this->exactly(2))
            ->method('link')
            ->willReturnCallback(static function (string $locale, Context $context, bool $activate) use (&$linked): void {
                static::assertTrue($activate);
                $linked[] = $locale;
            });

        $response = $this->createController($metadataStore, $translationLoader)->install(
            new InstallTranslationRequest(locales: ['fr-FR', 'es-ES']),
            $this->context()
        );

        static::assertSame(['fr-FR', 'es-ES'], $linked);

        $content = $this->decode($response);
        static::assertSame(['fr-FR'], $content['updated']);
        static::assertSame(['es-ES'], $content['skipped']);
        static::assertSame([], $content['unavailable']);
    }

    public function testInstallLinksCurrentLocaleWhoseLanguageIsMissing(): void
    {
        // The files are current, so nothing is downloaded — but the language may have been removed since,
        // for example by a database restore, and installing has to bring it back.
        $metadataStore = $this->createMock(TranslationMetadataStore::class);
        $metadataStore->method('getUpdatedLocalMetadata')->willReturn($this->metadataCollection(['fr-FR' => false]));
        $metadataStore->expects($this->never())->method('save');

        $translationLoader = $this->createMock(AbstractTranslationLoader::class);
        $translationLoader->method('hasTranslationFiles')->willReturn(true);
        $translationLoader->expects($this->never())->method('download');
        $translationLoader->expects($this->once())->method('link')->with('fr-FR', static::isInstanceOf(Context::class), true);

        $response = $this->createController($metadataStore, $translationLoader)->install(
            new InstallTranslationRequest(locales: ['fr-FR']),
            $this->context()
        );

        $content = $this->decode($response);
        static::assertSame([], $content['updated']);
        static::assertSame(['fr-FR'], $content['skipped']);
        static::assertSame([], $content['unavailable']);
    }

    public function testInstallDoesNotReportALocaleWithFilesAsUnavailable(): void
    {
        // Offline provisioning creates no metadata entry, so the remote knows nothing about this locale.
        // Its files are on the filesystem though, so it can be installed and must not be reported unavailable.
        $metadataStore = static::createStub(TranslationMetadataStore::class);
        $metadataStore->method('getUpdatedLocalMetadata')->willReturn(new MetadataCollection());

        $translationLoader = $this->createMock(AbstractTranslationLoader::class);
        $translationLoader->method('hasTranslationFiles')->willReturn(true);
        $translationLoader->expects($this->never())->method('download');
        $translationLoader->expects($this->once())->method('link')->with('fr-FR', static::isInstanceOf(Context::class), true);

        $response = $this->createController($metadataStore, $translationLoader)->install(
            new InstallTranslationRequest(locales: ['fr-FR']),
            $this->context()
        );

        $content = $this->decode($response);
        static::assertSame([], $content['unavailable']);
    }

    public function testInstallDownloadsCurrentLocaleWhoseFilesAreMissing(): void
    {
        $metadataStore = static::createStub(TranslationMetadataStore::class);
        $metadataStore->method('getUpdatedLocalMetadata')->willReturn($this->metadataCollection(['fr-FR' => false]));

        $translationLoader = $this->createMock(AbstractTranslationLoader::class);
        $translationLoader->method('hasTranslationFiles')->willReturn(false);
        $translationLoader->expects($this->once())->method('download')->with('fr-FR');
        $translationLoader->expects($this->once())->method('link')->with('fr-FR', static::isInstanceOf(Context::class), true);

        $response = $this->createController($metadataStore, $translationLoader)->install(
            new InstallTranslationRequest(locales: ['fr-FR']),
            $this->context()
        );

        $content = $this->decode($response);
        static::assertSame(['fr-FR'], $content['updated']);
        static::assertSame([], $content['skipped']);
    }

    public function testInstallThrowsWhenNoRequestedLocaleCanBeInstalled(): void
    {
        // The remote metadata has no entry for the requested locale, so nothing could be installed for it.
        $metadataStore = static::createStub(TranslationMetadataStore::class);
        $metadataStore->method('getUpdatedLocalMetadata')->willReturn(new MetadataCollection());

        $translationLoader = $this->createMock(AbstractTranslationLoader::class);
        $translationLoader->method('hasTranslationFiles')->willReturn(false);
        $translationLoader->expects($this->never())->method('download');
        $translationLoader->expects($this->never())->method('link');

        $this->expectExceptionObject(SnippetException::translationsUnavailable(['fr-FR']));

        $this->createController($metadataStore, $translationLoader)->install(
            new InstallTranslationRequest(locales: ['fr-FR']),
            $this->context()
        );
    }

    public function testInstallStillReportsPartiallyUnavailableLocales(): void
    {
        // fr-FR can be installed, es-ES is configured but not offered remotely: install the former, report the latter.
        $metadataStore = static::createStub(TranslationMetadataStore::class);
        $metadataStore->method('getUpdatedLocalMetadata')->willReturn($this->metadataCollection(['fr-FR' => true]));

        $translationLoader = $this->createMock(AbstractTranslationLoader::class);
        $translationLoader->method('hasTranslationFiles')->willReturn(false);
        $translationLoader->expects($this->once())->method('download')->with('fr-FR');
        $translationLoader->expects($this->once())->method('link')->with('fr-FR', static::isInstanceOf(Context::class), true);

        $response = $this->createController($metadataStore, $translationLoader)->install(
            new InstallTranslationRequest(locales: ['fr-FR', 'es-ES']),
            $this->context()
        );

        $content = $this->decode($response);
        static::assertSame(['fr-FR'], $content['updated']);
        static::assertSame([], $content['skipped']);
        static::assertSame(['es-ES'], $content['unavailable']);
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
            ->method('link')
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
