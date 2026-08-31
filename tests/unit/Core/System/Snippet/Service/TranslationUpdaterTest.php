<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\DataTransfer\Metadata\MetadataCollection;
use Shopware\Core\System\Snippet\DataTransfer\Metadata\MetadataEntry;
use Shopware\Core\System\Snippet\Service\AbstractTranslationLoader;
use Shopware\Core\System\Snippet\Service\TranslationMetadataStore;
use Shopware\Core\System\Snippet\Service\TranslationUpdater;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(TranslationUpdater::class)]
class TranslationUpdaterTest extends TestCase
{
    public function testUpdateLoadsLocalesRequiringUpdateAndSaves(): void
    {
        $metadata = $this->metadataCollection(['de-DE' => true, 'es-ES' => false]);

        $loader = $this->createMock(AbstractTranslationLoader::class);
        $loader->expects($this->once())
            ->method('load')
            ->with('de-DE', static::isInstanceOf(Context::class), true);

        $store = $this->createMock(TranslationMetadataStore::class);
        $store->expects($this->once())->method('save')->with($metadata);

        $result = (new TranslationUpdater($loader, $store))->update($metadata, Context::createCLIContext());

        static::assertSame(['de-DE'], $result->updated);
        static::assertSame(['es-ES'], $result->skipped);
    }

    public function testUpdateSkipsLoadAndSaveWhenNothingRequiresUpdate(): void
    {
        $metadata = $this->metadataCollection(['de-DE' => false, 'es-ES' => false]);

        $loader = $this->createMock(AbstractTranslationLoader::class);
        $loader->expects($this->never())->method('load');

        $store = $this->createMock(TranslationMetadataStore::class);
        $store->expects($this->never())->method('save');

        $result = (new TranslationUpdater($loader, $store))->update($metadata, Context::createCLIContext());

        static::assertSame([], $result->updated);
        static::assertSame(['de-DE', 'es-ES'], $result->skipped);
    }

    public function testUpdatePassesActivateFlagToLoader(): void
    {
        $metadata = $this->metadataCollection(['de-DE' => true]);

        $loader = $this->createMock(AbstractTranslationLoader::class);
        $loader->expects($this->once())
            ->method('load')
            ->with('de-DE', static::isInstanceOf(Context::class), false);

        $store = static::createStub(TranslationMetadataStore::class);

        (new TranslationUpdater($loader, $store))->update($metadata, Context::createCLIContext(), false);
    }

    public function testUpdateInstalledRefreshesAllInstalledLocales(): void
    {
        $metadata = $this->metadataCollection(['de-DE' => true]);

        $loader = $this->createMock(AbstractTranslationLoader::class);
        $loader->expects($this->once())->method('load')->with('de-DE');

        $store = $this->createMock(TranslationMetadataStore::class);
        $store->method('getLocalMetadata')->willReturn($metadata);
        $store->expects($this->once())->method('getUpdatedLocalMetadata')->with(null)->willReturn($metadata);
        $store->expects($this->once())->method('save')->with($metadata);

        $result = (new TranslationUpdater($loader, $store))->updateInstalled(Context::createCLIContext());

        static::assertSame(['de-DE'], $result->updated);
    }

    public function testUpdateInstalledRestrictsRefreshToGivenLocales(): void
    {
        $installed = $this->metadataCollection(['de-DE' => false, 'es-ES' => false]);
        $updated = $this->metadataCollection(['de-DE' => true, 'es-ES' => false]);

        $loader = $this->createMock(AbstractTranslationLoader::class);
        $loader->expects($this->once())->method('load')->with('de-DE');

        $store = $this->createMock(TranslationMetadataStore::class);
        $store->method('getLocalMetadata')->willReturn($installed);
        $store->expects($this->once())->method('getUpdatedLocalMetadata')->with(['de-DE'])->willReturn($updated);
        $store->expects($this->once())->method('save')->with($updated);

        $result = (new TranslationUpdater($loader, $store))->updateInstalled(Context::createCLIContext(), ['de-DE']);

        static::assertSame(['de-DE'], $result->updated);
        static::assertSame(['es-ES'], $result->skipped);
    }

    public function testUpdateInstalledDoesNothingWhenGivenLocalesAreNotInstalled(): void
    {
        $installed = $this->metadataCollection(['de-DE' => false]);

        $loader = $this->createMock(AbstractTranslationLoader::class);
        $loader->expects($this->never())->method('load');

        $store = $this->createMock(TranslationMetadataStore::class);
        $store->method('getLocalMetadata')->willReturn($installed);
        $store->expects($this->never())->method('getUpdatedLocalMetadata');
        $store->expects($this->never())->method('save');

        $result = (new TranslationUpdater($loader, $store))->updateInstalled(Context::createCLIContext(), ['fr-FR']);

        static::assertSame([], $result->updated);
        static::assertSame([], $result->skipped);
    }

    public function testUpdateInstalledDoesNothingWhenNoLocaleInstalled(): void
    {
        $loader = $this->createMock(AbstractTranslationLoader::class);
        $loader->expects($this->never())->method('load');

        $store = $this->createMock(TranslationMetadataStore::class);
        $store->method('getLocalMetadata')->willReturn(new MetadataCollection());
        $store->expects($this->never())->method('getUpdatedLocalMetadata');
        $store->expects($this->never())->method('save');

        $result = (new TranslationUpdater($loader, $store))->updateInstalled(Context::createCLIContext());

        static::assertSame([], $result->updated);
        static::assertSame([], $result->skipped);
    }

    public function testPlanInstallPartitionsTheRequestedLocales(): void
    {
        $metadata = $this->metadataCollection(['de-DE' => true, 'es-ES' => false, 'it-IT' => false]);

        $loader = static::createStub(AbstractTranslationLoader::class);
        $loader->method('hasTranslationFiles')
            ->willReturnCallback(static fn (string $locale) => \in_array($locale, ['es-ES', 'fr-FR'], true));

        $plan = (new TranslationUpdater($loader, static::createStub(TranslationMetadataStore::class)))
            ->planInstall(['de-DE', 'es-ES', 'fr-FR', 'nl-NL', 'it-IT'], $metadata);

        // de-DE has something newer, it-IT is current but has lost its files
        static::assertSame(['de-DE', 'it-IT'], $plan->localesToDownload);
        // es-ES is current and present, fr-FR has files without a metadata entry
        static::assertSame(['es-ES', 'fr-FR'], $plan->localesToLink);
        // nl-NL is neither offered nor present
        static::assertSame(['nl-NL'], $plan->unavailableLocales);
        static::assertFalse($plan->nothingCanBeInstalled());
    }

    public function testPlanInstallReportsNothingInstallableWhenNoLocaleIsOfferedOrPresent(): void
    {
        $loader = static::createStub(AbstractTranslationLoader::class);
        $loader->method('hasTranslationFiles')->willReturn(false);

        $plan = (new TranslationUpdater($loader, static::createStub(TranslationMetadataStore::class)))
            ->planInstall(['de-DE', 'es-ES'], new MetadataCollection());

        static::assertSame(['de-DE', 'es-ES'], $plan->unavailableLocales);
        static::assertTrue($plan->nothingCanBeInstalled());
    }

    public function testInstallDownloadsThenLinksAndLeavesPersistingToTheCaller(): void
    {
        $metadata = $this->metadataCollection(['de-DE' => true, 'es-ES' => false]);

        $loader = $this->createMock(AbstractTranslationLoader::class);
        $loader->method('hasTranslationFiles')->willReturn(true);
        $loader->expects($this->once())->method('download')->with('de-DE');

        $linked = [];
        $loader->expects($this->exactly(2))
            ->method('link')
            ->willReturnCallback(static function (string $locale, Context $context, bool $activate) use (&$linked): void {
                static::assertTrue($activate);
                $linked[] = $locale;
            });

        $store = $this->createMock(TranslationMetadataStore::class);
        $store->expects($this->never())->method('save');

        $updater = new TranslationUpdater($loader, $store);
        $result = $updater->install($updater->planInstall(['de-DE', 'es-ES'], $metadata), $metadata, Context::createCLIContext());

        static::assertSame(['de-DE', 'es-ES'], $linked);
        static::assertSame(['de-DE'], $result->updated);
        static::assertSame(['es-ES'], $result->skipped);
    }

    public function testInstallReportsEveryLocaleToTheProgressCallback(): void
    {
        $metadata = $this->metadataCollection(['de-DE' => true, 'es-ES' => false]);

        $loader = static::createStub(AbstractTranslationLoader::class);
        $loader->method('hasTranslationFiles')->willReturn(true);

        $updater = new TranslationUpdater($loader, static::createStub(TranslationMetadataStore::class));

        $reported = [];
        $updater->install(
            $updater->planInstall(['de-DE', 'es-ES'], $metadata),
            $metadata,
            Context::createCLIContext(),
            true,
            static function (string $locale) use (&$reported): void {
                $reported[] = $locale;
            },
        );

        static::assertSame(['de-DE', 'es-ES'], $reported);
    }

    public function testInstallPassesActivateFalseToTheLoader(): void
    {
        $metadata = $this->metadataCollection(['de-DE' => false]);

        $loader = $this->createMock(AbstractTranslationLoader::class);
        $loader->method('hasTranslationFiles')->willReturn(true);
        $loader->expects($this->once())->method('link')->with('de-DE', static::isInstanceOf(Context::class), false);

        $updater = new TranslationUpdater($loader, static::createStub(TranslationMetadataStore::class));
        $updater->install($updater->planInstall(['de-DE'], $metadata), $metadata, Context::createCLIContext(), false);
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
}
