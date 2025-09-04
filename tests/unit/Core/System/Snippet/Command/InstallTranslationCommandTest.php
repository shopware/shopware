<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Command;

use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Command\InstallTranslationCommand;
use Shopware\Core\System\Snippet\DataTransfer\Language\LanguageCollection;
use Shopware\Core\System\Snippet\DataTransfer\Metadata\MetadataCollection;
use Shopware\Core\System\Snippet\DataTransfer\Metadata\MetadataEntry;
use Shopware\Core\System\Snippet\DataTransfer\PluginMapping\PluginMappingCollection;
use Shopware\Core\System\Snippet\Service\TranslationLoader;
use Shopware\Core\System\Snippet\Service\TranslationMetadataLoader;
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

    private TranslationMetadataLoader&MockObject $metadataLoader;

    private TranslationConfig $config;

    protected function setUp(): void
    {
        $this->translationLoader = $this->createMock(TranslationLoader::class);
        $this->metadataLoader = $this->createMock(TranslationMetadataLoader::class);
        $this->config = new TranslationConfig(
            new Uri('http://localhost:8000'),
            ['en-GB', 'es-ES', 'de-DE'],
            [],
            new LanguageCollection(),
            new PluginMappingCollection(),
            new Uri('http://localhost:8000/metadata.json'),
        );
    }

    public function testExecuteThrowsExceptionWithoutArguments(): void
    {
        $command = $this->getCommand();
        $tester = new CommandTester($command);

        static::expectException(SnippetException::class);
        static::expectExceptionMessage('You must specify either --all or --locales to run the InstallTranslationCommand.');
        $tester->execute([]);
    }

    public function testExecuteThrowsExceptionWithInvalidLocales(): void
    {
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
            ->method('load')
            ->willReturnCallback(function (string $locale, Context $context, bool $activate): void {
                $expectedLocales = ['en-GB', 'es-ES'];

                static::assertTrue(\in_array($locale, $expectedLocales, true));
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

        $this->translationLoader->expects($this->exactly(1))
            ->method('load')
            ->willReturnCallback(function (string $locale): void {
                $expectedLocales = ['es-ES'];

                static::assertTrue(\in_array($locale, $expectedLocales, true));
            });

        $command = $this->getCommand();
        $tester = new CommandTester($command);

        $tester->execute(['--locales' => 'en-GB,es-ES,de-DE']);
        $tester->assertCommandIsSuccessful();

        $output = $tester->getDisplay();
        static::assertStringContainsString('The following locales are already up to date and will be skipped: en-GB, de-DE', $output);
        static::assertStringContainsString('Saving translation metadata...', $output);
        static::assertStringContainsString('Translation metadata saved successfully.', $output);
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

        $this->metadataLoader->expects($this->once())
            ->method('save')
            ->willThrowException(new \Exception('Something went wrong'));

        $command = $this->getCommand();
        $tester = new CommandTester($command);

        $tester->execute(['--locales' => 'es-ES']);
        $output = $tester->getDisplay();

        static::assertStringContainsString('Saving translation metadata...', $output);
        static::assertStringContainsString('An error occurred while saving metadata: "Something went wrong"', $output);
    }

    public function testCommandSkipsLoadingIfEverythingIsUpToDate(): void
    {
        $collection = new MetadataCollection([
            MetadataEntry::create([
                'locale' => 'es-ES',
                'updatedAt' => '2024-01-01T00:00:00+00:00',
                'progress' => 100,
            ]),
        ]);

        $this->initMetadataLoader($collection);

        $command = $this->getCommand();
        $tester = new CommandTester($command);

        $tester->execute(['--locales' => 'es-ES']);
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

        $this->translationLoader
            ->expects($this->once())
            ->method('load')
            ->willReturnCallback(function (string $locale, Context $context, bool $activate): void {
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
        $this->metadataLoader->expects($this->once())
            ->method('getUpdatedMetadata')
            ->willThrowException(new \Exception('Unable to fetch metadata'));

        $command = $this->getCommand();
        $tester = new CommandTester($command);

        $tester->execute(['--locales' => 'en-GB']);
        $output = $tester->getDisplay();

        static::assertStringContainsString('An error occurred while fetching metadata: "Unable to fetch metadata"', $output);
        static::assertSame(InstallTranslationCommand::FAILURE, $tester->getStatusCode());
    }

    private function getCommand(): InstallTranslationCommand
    {
        return new InstallTranslationCommand($this->translationLoader, $this->config, $this->metadataLoader);
    }

    private function initMetadataLoader(MetadataCollection $collection): void
    {
        $this->metadataLoader->expects($this->once())
            ->method('getUpdatedMetadata')
            ->willReturn($collection);
    }
}
