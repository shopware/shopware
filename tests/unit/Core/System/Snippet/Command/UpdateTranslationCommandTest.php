<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Command\UpdateTranslationCommand;
use Shopware\Core\System\Snippet\DataTransfer\Metadata\MetadataCollection;
use Shopware\Core\System\Snippet\DataTransfer\Metadata\MetadataEntry;
use Shopware\Core\System\Snippet\Service\TranslationLoader;
use Shopware\Core\System\Snippet\Service\TranslationMetadataStore;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(UpdateTranslationCommand::class)]
class UpdateTranslationCommandTest extends TestCase
{
    private TranslationLoader&MockObject $translationLoader;

    private TranslationMetadataStore&MockObject $metadataStore;

    protected function setUp(): void
    {
        $this->translationLoader = $this->createMock(TranslationLoader::class);
        $this->metadataStore = $this->createMock(TranslationMetadataStore::class);
    }

    public function testExecuteUpdatesAllInstalledTranslations(): void
    {
        $command = $this->getCommand();
        $tester = new CommandTester($command);

        $pl = MetadataEntry::create([
            'locale' => 'pl-PL',
            'updatedAt' => '2024-01-01T00:00:00+00:00',
            'progress' => 100,
        ]);

        $es = MetadataEntry::create([
            'locale' => 'es-ES',
            'updatedAt' => '2024-01-01T00:00:00+00:00',
            'progress' => 100,
        ]);

        $metadataCollection = new MetadataCollection([$pl, $es]);
        $metadataCollection->get('pl-PL')?->markForUpdate();
        $metadataCollection->get('es-ES')?->markForUpdate();

        $this->initMetadataLoader($metadataCollection);

        $this->translationLoader->expects($this->exactly(2))
            ->method('load')
            ->willReturnCallback(static function (string $locale): void {
                $expectedLocales = ['pl-PL', 'es-ES'];

                static::assertTrue(\in_array($locale, $expectedLocales, true));
            });

        $this->metadataStore->expects($this->once())
            ->method('save')
            ->with($metadataCollection);

        $tester->execute([]);
        $tester->assertCommandIsSuccessful();

        $output = $tester->getDisplay();
        static::assertStringContainsString('1/2 -- Updating translations for locale: pl-PL', $output);
        static::assertStringContainsString('2/2 -- Updating translations for locale: es-ES', $output);
        static::assertStringContainsString('Saving translation metadata...', $output);
        static::assertStringContainsString('Translation metadata saved successfully.', $output);
    }

    public function testExecuteWithNoTranslationsToUpdate(): void
    {
        $command = $this->getCommand();
        $tester = new CommandTester($command);

        $pl = MetadataEntry::create([
            'locale' => 'pl-PL',
            'updatedAt' => '2024-01-01T00:00:00+00:00',
            'progress' => 100,
        ]);

        $es = MetadataEntry::create([
            'locale' => 'es-ES',
            'updatedAt' => '2024-01-01T00:00:00+00:00',
            'progress' => 100,
        ]);

        $metadataCollection = new MetadataCollection([$pl, $es]);

        $this->initMetadataLoader($metadataCollection);

        $this->translationLoader->expects($this->never())
            ->method('load');

        $this->metadataStore->expects($this->never())
            ->method('save');

        $tester->execute([]);
        $tester->assertCommandIsSuccessful();

        $output = $tester->getDisplay();
        static::assertStringContainsString('All translations are already up to date.', $output);
    }

    public function testExecuteWithPartialTranslationsToUpdate(): void
    {
        $command = $this->getCommand();
        $tester = new CommandTester($command);

        $pl = MetadataEntry::create([
            'locale' => 'pl-PL',
            'updatedAt' => '2024-01-01T00:00:00+00:00',
            'progress' => 100,
        ]);

        $es = MetadataEntry::create([
            'locale' => 'es-ES',
            'updatedAt' => '2024-01-01T00:00:00+00:00',
            'progress' => 100,
        ]);

        $fr = MetadataEntry::create([
            'locale' => 'fr-FR',
            'updatedAt' => '2024-01-01T00:00:00+00:00',
            'progress' => 100,
        ]);

        $metadataCollection = new MetadataCollection([$pl, $es, $fr]);
        $metadataCollection->get('pl-PL')?->markForUpdate();

        $this->initMetadataLoader($metadataCollection);

        $this->translationLoader->expects($this->once())
            ->method('load')
            ->with('pl-PL');

        $this->metadataStore->expects($this->once())
            ->method('save')
            ->with($metadataCollection);

        $tester->execute([]);
        $tester->assertCommandIsSuccessful();

        $output = $tester->getDisplay();
        static::assertStringContainsString('The following locales are already up to date and will be skipped: es-ES, fr-FR', $output);
        static::assertStringContainsString('1/1 -- Updating translations for locale: pl-PL', $output);
        static::assertStringContainsString('Saving translation metadata...', $output);
        static::assertStringContainsString('Translation metadata saved successfully.', $output);
    }

    private function getCommand(): UpdateTranslationCommand
    {
        return new UpdateTranslationCommand($this->translationLoader, $this->metadataStore);
    }

    private function initMetadataLoader(MetadataCollection $collection): void
    {
        $this->metadataStore->expects($this->once())
            ->method('getUpdatedLocalMetadata')
            ->willReturn($collection);
    }
}
