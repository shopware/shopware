<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Command;

use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Command\InstallTranslationCommand;
use Shopware\Core\System\Snippet\DataTransfer\Language\Language;
use Shopware\Core\System\Snippet\DataTransfer\Language\LanguageCollection;
use Shopware\Core\System\Snippet\DataTransfer\Metadata\MetadataCollection;
use Shopware\Core\System\Snippet\DataTransfer\Metadata\MetadataEntry;
use Shopware\Core\System\Snippet\DataTransfer\PluginMapping\PluginMappingCollection;
use Shopware\Core\System\Snippet\Service\TranslationLoader;
use Shopware\Core\System\Snippet\Service\TranslationMetadataStore;
use Shopware\Core\System\Snippet\Service\TranslationUpdater;
use Shopware\Core\System\Snippet\SnippetException;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(InstallTranslationCommand::class)]
class InstallTranslationCommandTest extends TestCase
{
    private TranslationLoader&MockObject $translationLoader;

    private TranslationMetadataStore&MockObject $metadataStore;

    private TranslationConfig $config;

    protected function setUp(): void
    {
        $this->translationLoader = $this->createMock(TranslationLoader::class);
        $this->metadataStore = $this->createMock(TranslationMetadataStore::class);
        $this->config = new TranslationConfig(
            new Uri('http://localhost:8000'),
            ['en-GB', 'es-ES', 'de-DE'],
            [],
            new LanguageCollection(),
            new PluginMappingCollection(),
            new Uri('http://localhost:8000/metadata.json'),
            ['it-IT'],
        );
    }

    public function testExecuteThrowsExceptionWithoutArguments(): void
    {
        $this->translationLoader->expects($this->never())->method('download');
        $this->metadataStore->expects($this->never())->method('getUpdatedLocalMetadata');

        $command = $this->getCommand();
        $tester = new CommandTester($command);

        $this->expectExceptionObject(SnippetException::noArgumentsProvided());
        $tester->execute([], ['interactive' => false]);
    }

    public function testExecutePromptsInteractivelyWhenNoLocalesProvided(): void
    {
        $this->config = new TranslationConfig(
            new Uri('http://localhost:8000'),
            ['en-GB', 'es-ES', 'de-DE'],
            [],
            new LanguageCollection([
                new Language('en-GB', 'English'),
                new Language('es-ES', 'Español'),
                new Language('de-DE', 'Deutsch'),
            ]),
            new PluginMappingCollection(),
            new Uri('http://localhost:8000/metadata.json'),
            [],
        );

        $collection = new MetadataCollection([
            MetadataEntry::create([
                'locale' => 'de-DE',
                'updatedAt' => '2024-01-01T00:00:00+00:00',
                'progress' => 100,
            ]),
            MetadataEntry::create([
                'locale' => 'es-ES',
                'updatedAt' => '2024-01-01T00:00:00+00:00',
                'progress' => 100,
            ]),
        ]);
        $collection->get('de-DE')?->markForUpdate();
        $collection->get('es-ES')?->markForUpdate();

        $this->initMetadataLoader($collection);

        $this->translationLoader->expects($this->exactly(2))
            ->method('download')
            ->willReturnCallback(static function (string $locale): void {
                static::assertContains($locale, ['de-DE', 'es-ES']);
            });
        $this->translationLoader->expects($this->exactly(2))->method('link');

        $tester = new CommandTester($this->getCommand());
        $tester->setInputs(['de-DE,es-ES']);

        $tester->execute([], ['interactive' => true]);
        $tester->assertCommandIsSuccessful();

        static::assertStringContainsString('Select one or more locales to install', $tester->getDisplay());
    }

    public function testExecuteThrowsExceptionWithInvalidLocales(): void
    {
        $this->translationLoader->expects($this->never())->method('download');
        $this->metadataStore->expects($this->never())->method('getUpdatedLocalMetadata');

        $command = $this->getCommand();
        $tester = new CommandTester($command);

        static::expectException(SnippetException::class);
        $tester->execute(['--locales' => 'invalid-locale']);
    }

    public function testExecuteTranslationCommandRunsSuccessful(): void
    {
        $elements = [
            MetadataEntry::create([
                'locale' => 'en-GB',
                'updatedAt' => '2024-01-01T00:00:00+00:00',
                'progress' => 100,
            ]),
            MetadataEntry::create([
                'locale' => 'es-ES',
                'updatedAt' => '2024-01-01T00:00:00+00:00',
                'progress' => 100,
            ]),
        ];

        $collection = new MetadataCollection($elements);
        $collection->get('en-GB')?->markForUpdate();
        $collection->get('es-ES')?->markForUpdate();

        $this->initMetadataLoader($collection);

        $this->translationLoader->expects($this->exactly(2))
            ->method('download')
            ->willReturnCallback(static function (string $locale): void {
                static::assertContains($locale, ['en-GB', 'es-ES']);
            });

        $this->translationLoader->expects($this->exactly(2))
            ->method('link')
            ->willReturnCallback(static function (string $locale, Context $context, bool $activate): void {
                static::assertContains($locale, ['en-GB', 'es-ES']);
                static::assertTrue($activate, 'Default should activate when --skip-activation is not provided');
            });

        $command = $this->getCommand();
        $tester = new CommandTester($command);

        $tester->execute(['--locales' => 'en-GB,es-ES']);
        $tester->assertCommandIsSuccessful();
    }

    public function testCommandInstallsOnlyLanguagesRequiringUpdate(): void
    {
        $collection = new MetadataCollection([
            MetadataEntry::create([
                'locale' => 'es-ES',
                'updatedAt' => '2024-01-01T00:00:00+00:00',
                'progress' => 100,
            ]),
            MetadataEntry::create([
                'locale' => 'en-GB',
                'updatedAt' => '2024-01-01T00:00:00+00:00',
                'progress' => 100,
            ]),
            MetadataEntry::create([
                'locale' => 'de-DE',
                'updatedAt' => '2024-01-01T00:00:00+00:00',
                'progress' => 100,
            ]),
        ]);

        $collection->get('es-ES')?->markForUpdate();

        $this->initMetadataLoader($collection);
        $this->translationLoader->method('hasTranslationFiles')->willReturn(true);

        $this->translationLoader->expects($this->exactly(1))
            ->method('download')
            ->willReturnCallback(static function (string $locale): void {
                static::assertSame('es-ES', $locale);
            });

        // The other two are not skipped, only their download is: their language and snippet
        // set are ensured just the same.
        $linked = [];
        $this->translationLoader->expects($this->exactly(3))
            ->method('link')
            ->willReturnCallback(static function (string $locale) use (&$linked): void {
                $linked[] = $locale;
            });

        $command = $this->getCommand();
        $tester = new CommandTester($command);

        $tester->execute(['--locales' => 'en-GB,es-ES,de-DE']);
        $tester->assertCommandIsSuccessful();

        static::assertSame(['es-ES', 'en-GB', 'de-DE'], $linked);

        $output = $tester->getDisplay();
        static::assertStringContainsString('The following locales are installed from their existing translation files, without downloading: en-GB, de-DE', $output);
        static::assertStringContainsString('Saving translation metadata...', $output);
        static::assertStringContainsString('Translation metadata saved successfully.', $output);
    }

    public function testCommandDownloadsUpToDateLocalesWhoseFilesAreMissing(): void
    {
        $collection = new MetadataCollection([
            MetadataEntry::create([
                'locale' => 'es-ES',
                'updatedAt' => '2024-01-01T00:00:00+00:00',
                'progress' => 100,
            ]),
        ]);

        $this->initMetadataLoader($collection);

        // Metadata says the locale is current, but nothing is on the filesystem, so linking
        // would leave a language with no translations behind it.
        $this->translationLoader->method('hasTranslationFiles')->willReturn(false);

        $this->translationLoader->expects($this->once())->method('download')->with('es-ES');
        $this->translationLoader->expects($this->once())->method('link')->with('es-ES');

        $tester = new CommandTester($this->getCommand());
        $tester->execute(['--locales' => 'es-ES']);
        $tester->assertCommandIsSuccessful();

        static::assertStringNotContainsString('installed from their existing translation files', $tester->getDisplay());
    }

    public function testOfflineInstallCreatesLanguagesWithoutTouchingTheMetadata(): void
    {
        $this->metadataStore->expects($this->never())->method('getUpdatedLocalMetadata');
        $this->metadataStore->expects($this->never())->method('save');

        $this->translationLoader->method('hasTranslationFiles')->willReturn(true);
        $this->translationLoader->expects($this->never())->method('load');

        $linked = [];
        $this->translationLoader->expects($this->exactly(2))
            ->method('link')
            ->willReturnCallback(static function (string $locale, Context $context, bool $activate) use (&$linked): void {
                static::assertTrue($activate);
                $linked[] = $locale;
            });

        $tester = new CommandTester($this->getCommand());
        $tester->execute(['--locales' => 'en-GB,de-DE', '--offline' => true]);
        $tester->assertCommandIsSuccessful();

        static::assertSame(['en-GB', 'de-DE'], $linked);
    }

    public function testCommandOutputsErrorIfMetadataCannotBeWritten(): void
    {
        $collection = new MetadataCollection([
            MetadataEntry::create([
                'locale' => 'es-ES',
                'updatedAt' => '2024-01-01T00:00:00+00:00',
                'progress' => 100,
            ]),
        ]);

        $collection->get('es-ES')?->markForUpdate();
        $this->initMetadataLoader($collection);

        $this->translationLoader->expects($this->once())->method('download');

        $this->metadataStore->expects($this->once())
            ->method('save')
            ->willThrowException(new \Exception('Something went wrong'));

        $command = $this->getCommand();
        $tester = new CommandTester($command);

        $tester->execute(['--locales' => 'es-ES']);
        $output = $tester->getDisplay();

        static::assertStringContainsString('Saving translation metadata...', $output);
        static::assertStringContainsString('An error occurred while saving metadata: "Something went wrong"', $output);
    }

    public function testCommandSkipsTheDownloadButStillInstallsIfEverythingIsUpToDate(): void
    {
        $collection = new MetadataCollection([
            MetadataEntry::create([
                'locale' => 'es-ES',
                'updatedAt' => '2024-01-01T00:00:00+00:00',
                'progress' => 100,
            ]),
        ]);

        $this->initMetadataLoader($collection);
        $this->translationLoader->method('hasTranslationFiles')->willReturn(true);

        // Nothing is re-fetched, but the language and snippet set are still ensured: a locale
        // whose files are current may well have no language row yet.
        $this->translationLoader->expects($this->never())->method('download');
        $this->translationLoader->expects($this->once())->method('link');

        $command = $this->getCommand();
        $tester = new CommandTester($command);

        $tester->execute(['--locales' => 'es-ES']);
        $tester->assertCommandIsSuccessful();
        $output = $tester->getDisplay();

        static::assertStringContainsString('All translations are already up to date.', $output);
    }

    public function testExecuteRunsSuccessfulWithSkipActivation(): void
    {
        $collection = new MetadataCollection([
            MetadataEntry::create([
                'locale' => 'en-GB',
                'updatedAt' => '2024-01-01T00:00:00+00:00',
                'progress' => 100,
            ]),
        ]);

        $collection->get('en-GB')?->markForUpdate();
        $this->initMetadataLoader($collection);

        $this->translationLoader->expects($this->once())->method('download')->with('en-GB');
        $this->translationLoader
            ->expects($this->once())
            ->method('link')
            ->willReturnCallback(static function (string $locale, Context $context, bool $activate): void {
                static::assertSame('en-GB', $locale);
                static::assertFalse($activate, 'Should pass activate=false when --skip-activation is used');
            });

        $command = $this->getCommand();
        $tester = new CommandTester($command);

        $tester->execute(['--locales' => 'en-GB', '--skip-activation' => true]);
        $tester->assertCommandIsSuccessful();
    }

    public function testCommandFailsIfMetadataCannotBeLoaded(): void
    {
        $this->translationLoader->expects($this->never())->method('download');

        $this->metadataStore->expects($this->once())
            ->method('getUpdatedLocalMetadata')
            ->willThrowException(new \Exception('Unable to fetch metadata'));

        $command = $this->getCommand();
        $tester = new CommandTester($command);

        $tester->execute(['--locales' => 'en-GB']);
        $output = $tester->getDisplay();

        static::assertStringContainsString('An error occurred while fetching metadata: "Unable to fetch metadata"', $output);
        static::assertSame(InstallTranslationCommand::FAILURE, $tester->getStatusCode());
    }

    public function testCommandLeavesOutLocalesTheRepositoryDoesNotOffer(): void
    {
        $collection = new MetadataCollection([
            MetadataEntry::create([
                'locale' => 'es-ES',
                'updatedAt' => '2024-01-01T00:00:00+00:00',
                'progress' => 100,
            ]),
        ]);

        $this->initMetadataLoader($collection);
        $this->translationLoader->method('hasTranslationFiles')->willReturn(false);

        // en-GB has neither a metadata entry nor files, so installing it would create a language
        // without any translations behind it.
        $this->translationLoader->expects($this->once())
            ->method('download')
            ->willReturnCallback(static function (string $locale): void {
                static::assertSame('es-ES', $locale);
            });
        $this->translationLoader->expects($this->once())->method('link')->with('es-ES');

        $tester = new CommandTester($this->getCommand());
        $tester->execute(['--locales' => 'en-GB,es-ES']);
        $tester->assertCommandIsSuccessful();

        static::assertStringContainsString('No translations are available for the following locales, they will not be installed: en-GB', $tester->getDisplay());
    }

    public function testCommandInstallsLocaleWithoutMetadataEntryButWithFiles(): void
    {
        $this->initMetadataLoader(new MetadataCollection());
        $this->translationLoader->method('hasTranslationFiles')->willReturn(true);

        $this->translationLoader->expects($this->never())->method('download');
        $this->translationLoader->expects($this->once())
            ->method('link')
            ->willReturnCallback(static function (string $locale): void {
                static::assertSame('en-GB', $locale);
            });

        $tester = new CommandTester($this->getCommand());
        $tester->execute(['--locales' => 'en-GB']);
        $tester->assertCommandIsSuccessful();
    }

    public function testCommandFailsIfNoRequestedLocaleCanBeInstalled(): void
    {
        $this->initMetadataLoader(new MetadataCollection());
        $this->translationLoader->method('hasTranslationFiles')->willReturn(false);

        $this->translationLoader->expects($this->never())->method('download');
        $this->translationLoader->expects($this->never())->method('link');
        $this->metadataStore->expects($this->never())->method('save');

        $tester = new CommandTester($this->getCommand());

        $this->expectExceptionObject(SnippetException::translationsUnavailable(['en-GB', 'de-DE']));
        $tester->execute(['--locales' => 'en-GB,de-DE']);
    }

    public function testOfflineInstallFailsWithEveryMissingLocaleBeforeLinkingAnything(): void
    {
        $this->metadataStore->expects($this->never())->method('getUpdatedLocalMetadata');
        $this->metadataStore->expects($this->never())->method('save');
        $this->translationLoader->method('hasTranslationFiles')->willReturn(false);

        $this->translationLoader->expects($this->never())->method('link');
        $this->translationLoader->expects($this->never())->method('download');

        $tester = new CommandTester($this->getCommand());

        $this->expectExceptionObject(SnippetException::translationsUnavailable(['en-GB', 'de-DE']));
        $tester->execute(['--locales' => 'en-GB,de-DE', '--offline' => true]);
    }

    public function testOfflineInstallLinksNothingWhenOneLocaleIsMissing(): void
    {
        $this->metadataStore->expects($this->never())->method('getUpdatedLocalMetadata');
        $this->metadataStore->expects($this->never())->method('save');
        $this->translationLoader->method('hasTranslationFiles')
            ->willReturnCallback(static fn (string $locale) => $locale === 'en-GB');

        $this->translationLoader->expects($this->never())->method('link');

        $tester = new CommandTester($this->getCommand());

        $this->expectExceptionObject(SnippetException::translationsUnavailable(['de-DE']));
        $tester->execute(['--locales' => 'en-GB,de-DE', '--offline' => true]);
    }

    public function testOfflineInstallWithAllLinksEveryConfiguredLocale(): void
    {
        $this->metadataStore->expects($this->never())->method('getUpdatedLocalMetadata');
        $this->metadataStore->expects($this->never())->method('save');
        $this->translationLoader->method('hasTranslationFiles')->willReturn(true);
        $this->translationLoader->expects($this->never())->method('load');

        $linked = [];
        $this->translationLoader->expects($this->exactly(3))
            ->method('link')
            ->willReturnCallback(static function (string $locale) use (&$linked): void {
                $linked[] = $locale;
            });

        $tester = new CommandTester($this->getCommand());
        $tester->execute(['--all' => true, '--offline' => true]);
        $tester->assertCommandIsSuccessful();

        static::assertSame(['en-GB', 'es-ES', 'de-DE'], $linked);
    }

    public function testOfflineInstallWithAllSkipsLocalesWithoutFiles(): void
    {
        $this->metadataStore->expects($this->never())->method('getUpdatedLocalMetadata');
        $this->metadataStore->expects($this->never())->method('save');
        $this->translationLoader->method('hasTranslationFiles')
            ->willReturnCallback(static fn (string $locale) => $locale !== 'es-ES');

        // --all means "everything that is provisioned": one locale the repository never offered must
        // not make the command unusable for the others.
        $linked = [];
        $this->translationLoader->expects($this->exactly(2))
            ->method('link')
            ->willReturnCallback(static function (string $locale) use (&$linked): void {
                $linked[] = $locale;
            });

        $tester = new CommandTester($this->getCommand());
        $tester->execute(['--all' => true, '--offline' => true]);
        $tester->assertCommandIsSuccessful();

        static::assertSame(['en-GB', 'de-DE'], $linked);
        static::assertStringContainsString('No translation files are present for the following locales, they will not be installed: es-ES', $tester->getDisplay());
    }

    public function testOfflineInstallWithAllFailsWhenNoConfiguredLocaleHasFiles(): void
    {
        $this->metadataStore->expects($this->never())->method('getUpdatedLocalMetadata');
        $this->metadataStore->expects($this->never())->method('save');
        $this->translationLoader->method('hasTranslationFiles')->willReturn(false);
        $this->translationLoader->expects($this->never())->method('link');

        $tester = new CommandTester($this->getCommand());

        $this->expectExceptionObject(SnippetException::translationsUnavailable(['en-GB', 'es-ES', 'de-DE']));
        $tester->execute(['--all' => true, '--offline' => true]);
    }

    private function getCommand(): InstallTranslationCommand
    {
        return new InstallTranslationCommand(
            $this->config,
            $this->metadataStore,
            new TranslationUpdater($this->translationLoader, $this->metadataStore),
        );
    }

    private function initMetadataLoader(MetadataCollection $collection): void
    {
        $this->metadataStore->expects($this->once())
            ->method('getUpdatedLocalMetadata')
            ->willReturn($collection);
    }
}
