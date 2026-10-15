<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem as ThemeFilesystem;
use Shopware\Storefront\Theme\Command\ThemeMigrateTranslationsCommand;
use Shopware\Storefront\Theme\Snippet\ThemeConfigSnippetGenerator;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\ThemeFilesystemResolver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ThemeMigrateTranslationsCommand::class)]
class ThemeMigrateTranslationsCommandTest extends TestCase
{
    private const SNIPPET_FILE = '/Resources/app/administration/src/snippet/en-GB.json';

    private Filesystem $filesystem;

    private string $themeDirectory;

    private StorefrontPluginConfiguration $configuration;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->themeDirectory = sys_get_temp_dir() . '/' . uniqid('SwagTheme', true);
        $this->filesystem->mkdir($this->themeDirectory . '/Resources');

        $this->configuration = new StorefrontPluginConfiguration('SwagTheme');
        $this->configuration->setIsTheme(true);

        $registry = static::createStub(StorefrontPluginRegistry::class);
        $registry->method('getConfigurations')->willReturn(new StorefrontPluginConfigurationCollection([$this->configuration]));

        $resolver = static::createStub(ThemeFilesystemResolver::class);
        $resolver->method('getFilesystemForStorefrontConfig')->willReturn(new ThemeFilesystem($this->themeDirectory));

        $this->commandTester = new CommandTester(new ThemeMigrateTranslationsCommand(
            $registry,
            $resolver,
            new ThemeConfigSnippetGenerator(static::createStub(StorefrontPluginRegistry::class)),
            $this->filesystem,
        ));
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->themeDirectory);
    }

    public function testUnknownThemeFails(): void
    {
        $exitCode = $this->commandTester->execute(['technical-name' => 'Unknown']);

        static::assertSame(Command::FAILURE, $exitCode);
        static::assertStringContainsString('Theme "Unknown" not found.', $this->commandTester->getDisplay());
    }

    public function testAppThemesGetTheirSnippetsIntoTheAppSnippetDirectory(): void
    {
        $this->writeThemeJson(['sw-logo' => ['label' => ['en-GB' => 'Logo']]]);
        $this->filesystem->dumpFile($this->themeDirectory . '/manifest.xml', '<manifest/>');

        $exitCode = $this->commandTester->execute(['technical-name' => 'SwagTheme']);
        $display = $this->commandTester->getDisplay();

        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertFileDoesNotExist($this->themeDirectory . self::SNIPPET_FILE);
        static::assertSame(
            ['sw-theme' => ['SwagTheme' => ['default' => ['default' => ['default' => ['sw-logo' => ['label' => 'Logo']]]]]]],
            $this->readJson($this->themeDirectory . '/Resources/app/administration/snippet/en-GB.json'),
        );
        static::assertStringContainsString('app:update SwagTheme', $display);
        static::assertStringNotContainsString('[WARNING]', $display);
    }

    public function testAppThemesWithoutEnglishSnippetsAreWarned(): void
    {
        $this->writeThemeJson(['sw-logo' => ['label' => ['de-DE' => 'Logo DE']]]);
        $this->filesystem->dumpFile($this->themeDirectory . '/manifest.xml', '<manifest/>');

        $this->commandTester->execute(['technical-name' => 'SwagTheme']);

        static::assertStringContainsString('App snippets require en-GB.json', $this->commandTester->getDisplay());
        static::assertFileExists($this->themeDirectory . '/Resources/app/administration/snippet/de-DE.json');
    }

    public function testThemeWithoutLegacyTranslationsWritesNothing(): void
    {
        $this->writeThemeJson(['sw-logo' => ['type' => 'media']]);

        $exitCode = $this->commandTester->execute(['technical-name' => 'SwagTheme']);

        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertStringContainsString('has no legacy translations', $this->commandTester->getDisplay());
        static::assertFileDoesNotExist($this->themeDirectory . self::SNIPPET_FILE);
    }

    public function testWritesOneSnippetFilePerLanguage(): void
    {
        $this->writeThemeJson([
            'sw-logo' => ['label' => ['en-GB' => 'Logo', 'de-DE' => 'Logo (DE)'], 'helpText' => ['en-GB' => 'Shown in the header']],
        ]);

        $exitCode = $this->commandTester->execute(['technical-name' => 'SwagTheme']);

        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertSame(
            ['sw-theme' => ['SwagTheme' => ['default' => ['default' => ['default' => ['sw-logo' => ['label' => 'Logo', 'helpText' => 'Shown in the header']]]]]]],
            $this->readJson($this->themeDirectory . self::SNIPPET_FILE),
        );
        static::assertSame(
            ['sw-theme' => ['SwagTheme' => ['default' => ['default' => ['default' => ['sw-logo' => ['label' => 'Logo (DE)']]]]]]],
            $this->readJson($this->themeDirectory . '/Resources/app/administration/src/snippet/de-DE.json'),
        );
        static::assertStringContainsString('re-run with --strip', $this->commandTester->getDisplay());
    }

    public function testExistingSnippetsAreKeptWhenMerging(): void
    {
        $this->writeThemeJson(['sw-logo' => ['label' => ['en-GB' => 'Generated']]]);
        $this->filesystem->dumpFile($this->themeDirectory . self::SNIPPET_FILE, \json_encode([
            'sw-theme' => ['SwagTheme' => ['default' => ['default' => ['default' => ['sw-logo' => ['label' => 'Maintained']]]]]],
            'swag-theme' => ['other' => 'untouched'],
        ], \JSON_THROW_ON_ERROR));

        $this->commandTester->execute(['technical-name' => 'SwagTheme']);

        static::assertSame(
            [
                'sw-theme' => ['SwagTheme' => ['default' => ['default' => ['default' => ['sw-logo' => ['label' => 'Maintained']]]]]],
                'swag-theme' => ['other' => 'untouched'],
            ],
            $this->readJson($this->themeDirectory . self::SNIPPET_FILE),
        );
    }

    public function testWarnsWhenTheThemeAlreadyMaintainsALanguageFile(): void
    {
        $this->writeThemeJson(['sw-logo' => ['label' => ['en-GB' => 'Logo', 'de-DE' => 'Logo DE']]]);
        $this->filesystem->dumpFile($this->themeDirectory . '/Resources/app/administration/src/snippet/en.json', '{}');

        $exitCode = $this->commandTester->execute(['technical-name' => 'SwagTheme']);
        $display = $this->commandTester->getDisplay();

        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertStringContainsString('[WARNING]', $display);
        static::assertStringContainsString('en-GB.json', $display);
        static::assertStringContainsString('en.json', $display);
        static::assertStringNotContainsString('de.json', $display);
    }

    public function testDoesNotWarnWithoutMaintainedLanguageFiles(): void
    {
        $this->writeThemeJson(['sw-logo' => ['label' => ['en-GB' => 'Logo']]]);

        $this->commandTester->execute(['technical-name' => 'SwagTheme']);

        static::assertStringNotContainsString('[WARNING]', $this->commandTester->getDisplay());
    }

    public function testWarnsAboutGroupLabelsNoFieldUses(): void
    {
        $this->configuration->setThemeJson([
            'name' => 'SwagTheme',
            'config' => [
                'blocks' => ['ghost' => ['label' => ['en-GB' => 'Nobody uses me']]],
                'fields' => ['sw-logo' => ['type' => 'media', 'block' => 'logos']],
            ],
        ]);

        $exitCode = $this->commandTester->execute(['technical-name' => 'SwagTheme']);
        $display = $this->commandTester->getDisplay();

        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertStringContainsString('[WARNING]', $display);
        static::assertStringContainsString('blocks.ghost', $display);
        static::assertStringContainsString('has no legacy translations', $display);
    }

    public function testDryRunWritesNothing(): void
    {
        $themeJson = $this->writeThemeJson(['sw-logo' => ['label' => ['en-GB' => 'Logo']]]);

        $exitCode = $this->commandTester->execute(['technical-name' => 'SwagTheme', '--dry-run' => true, '--strip' => true]);

        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertStringContainsString('Dry run, nothing was written.', $this->commandTester->getDisplay());
        static::assertFileDoesNotExist($this->themeDirectory . self::SNIPPET_FILE);
        static::assertSame($themeJson, $this->readJson($this->themeDirectory . '/Resources/theme.json'));
    }

    public function testStripRemovesLegacyTranslationsFromThemeJson(): void
    {
        $this->writeThemeJson(
            [
                'sw-logo' => [
                    'type' => 'media',
                    'tab' => 'brand',
                    'label' => ['en-GB' => 'Logo'],
                    'helpText' => ['en-GB' => 'Header logo'],
                    'custom' => ['options' => [['value' => 'a', 'label' => ['en-GB' => 'A']]]],
                ],
            ],
            ['brand' => ['label' => ['en-GB' => 'Brand']]],
        );

        $exitCode = $this->commandTester->execute(['technical-name' => 'SwagTheme', '--strip' => true]);

        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertSame(
            [
                'name' => 'SwagTheme',
                'config' => [
                    'fields' => [
                        'sw-logo' => [
                            'type' => 'media',
                            'tab' => 'brand',
                            'custom' => ['options' => [['value' => 'a']]],
                        ],
                    ],
                    'tabs' => ['brand' => []],
                ],
            ],
            $this->readJson($this->themeDirectory . '/Resources/theme.json'),
        );
        static::assertStringContainsString('"brand": {}', $this->filesystem->readFile($this->themeDirectory . '/Resources/theme.json'));
        static::assertFileExists($this->themeDirectory . self::SNIPPET_FILE);
    }

    /**
     * @param array<string, mixed> $fields
     * @param array<string, mixed> $tabs
     *
     * @return array<string, mixed>
     */
    private function writeThemeJson(array $fields, array $tabs = []): array
    {
        $config = ['fields' => $fields];
        if ($tabs !== []) {
            $config['tabs'] = $tabs;
        }

        $themeJson = ['name' => 'SwagTheme', 'config' => $config];
        $this->configuration->setThemeJson($themeJson);
        $this->filesystem->dumpFile($this->themeDirectory . '/Resources/theme.json', \json_encode($themeJson, \JSON_THROW_ON_ERROR));

        return $themeJson;
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        return \json_decode($this->filesystem->readFile($path), true, 512, \JSON_THROW_ON_ERROR);
    }
}
