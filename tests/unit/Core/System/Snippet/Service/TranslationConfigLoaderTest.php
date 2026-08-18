<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\Snippet\DataTransfer\Language\Language;
use Shopware\Core\System\Snippet\DataTransfer\PluginMapping\PluginMapping;
use Shopware\Core\System\Snippet\Service\TranslationConfigLoader;
use Shopware\Core\System\Snippet\SnippetException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(TranslationConfigLoader::class)]
class TranslationConfigLoaderTest extends TestCase
{
    private TestableTranslationConfigLoader $translationConfigLoader;

    protected function setUp(): void
    {
        $this->translationConfigLoader = new TestableTranslationConfigLoader(
            new Filesystem()
        );
    }

    public function testLoadTranslationConfig(): void
    {
        $config = $this->translationConfigLoader->load();

        static::assertSame(
            'https://raw.githubusercontent.com/shopware/translations/main/translations',
            $config->repositoryUrl->__toString()
        );

        $locales = $config->locales;
        static::assertIsArray($locales);
        static::assertContains('en-GB', $locales);

        $plugins = $config->plugins;
        static::assertIsArray($plugins);
        static::assertContains('SwagCommercial', $plugins);

        $languages = $config->languages;
        $language = $languages->get('en-GB');
        static::assertInstanceOf(Language::class, $language);
        static::assertSame('English (UK)', $language->name);

        $publisherMapping = $config->pluginMapping->get('SwagPublisher');
        static::assertInstanceOf(PluginMapping::class, $publisherMapping);
        static::assertSame('PluginPublisher', $publisherMapping->snippetName);

        $excludedLocales = $config->excludedLocales;
        static::assertSame(['it-IT'], $excludedLocales);
    }

    public function testConfigFileSettings(): void
    {
        static::assertSame($this->translationConfigLoader->getParentConfigFilename(), 'translation.yaml');
        static::assertStringEndsWith('/../../Resources', $this->translationConfigLoader->getParentRelativeConfigurationPath());
    }

    public function testThrowsOnInvalidUrl(): void
    {
        $this->translationConfigLoader->setConfigFileName('translation_invalid_url.yaml');

        $this->expectExceptionObject(SnippetException::invalidRepositoryUrl('invalid_url', new \Exception('"repository-url" must contain a schema and a host.')));
        $this->translationConfigLoader->load();
    }

    public function testThrowsOnBrokenUrl(): void
    {
        $this->translationConfigLoader->setConfigFileName('translation_broken_url.yaml');

        $this->expectExceptionObject(SnippetException::invalidRepositoryUrl('http://', new \Exception('Unable to parse URI: http://')));
        $this->translationConfigLoader->load();
    }

    public function testThrowsOnInvalidUrlType(): void
    {
        $this->translationConfigLoader->setConfigFileName('translation_non_string_url.yaml');

        $this->expectExceptionObject(SnippetException::invalidRepositoryUrl('4', new \Exception('"repository-url" in the translation config must be a string.')));
        $this->translationConfigLoader->load();
    }

    public function testThrowsOnEmptyUrl(): void
    {
        $this->translationConfigLoader->setConfigFileName('translation_empty_string_url.yaml');

        $this->expectExceptionObject(SnippetException::invalidRepositoryUrl('', new \Exception('"repository-url" in the translation config must not be empty.')));
        $this->translationConfigLoader->load();
    }

    public function testThrowsOnNonExistingConfigurationDirectory(): void
    {
        $this->translationConfigLoader->setRelativeConfigurationPath(__DIR__ . '/non-existing-directory');
        static::expectException(SnippetException::class);
        static::expectExceptionMessageMatches('#^Translation configuration directory does not exist: .*non-existing-directory"\.$#');
        $this->translationConfigLoader->load();
    }

    public function testThrowsOnNonExistingConfigurationFile(): void
    {
        $this->translationConfigLoader->setConfigFileName('non-existing-file');
        $this->expectExceptionObject(SnippetException::translationConfigurationFileDoesNotExist('non-existing-file'));
        $this->translationConfigLoader->load();
    }

    public function testThrowsOnEmptyConfigurationFile(): void
    {
        $this->translationConfigLoader->setConfigFileName('translation_empty.yaml');
        $this->expectExceptionObject(SnippetException::translationConfigurationFileIsEmpty('translation_empty.yaml'));
        $this->translationConfigLoader->load();
    }

    public function testGetDecoratedThrowsException(): void
    {
        static::expectException(DecorationPatternException::class);
        $this->translationConfigLoader->getDecorated();
    }

    public function testOverrideRepositoryUrl(): void
    {
        $loader = new TestableTranslationConfigLoader(new Filesystem(), ['repository_url' => 'https://example.com/repo']);

        static::assertSame('https://example.com/repo', $loader->load()->repositoryUrl->__toString());
    }

    public function testOverrideMetadataUrl(): void
    {
        $loader = new TestableTranslationConfigLoader(new Filesystem(), ['metadata_url' => 'https://example.com/metadata.json']);

        static::assertSame('https://example.com/metadata.json', $loader->load()->metadataUrl->__toString());
    }

    public function testOverridePluginsReplacesList(): void
    {
        $loader = new TestableTranslationConfigLoader(new Filesystem(), ['plugins' => ['MyCustomPlugin']]);

        static::assertSame(['MyCustomPlugin'], $loader->load()->plugins);
    }

    public function testOverrideLanguagesReplacesList(): void
    {
        $loader = new TestableTranslationConfigLoader(new Filesystem(), [
            'languages' => [['name' => 'Italiano', 'locale' => 'it-IT']],
        ]);

        $config = $loader->load();

        static::assertSame(['it-IT'], $config->locales);
        $language = $config->languages->get('it-IT');
        static::assertInstanceOf(Language::class, $language);
        static::assertSame('Italiano', $language->name);
        static::assertNull($config->languages->get('en-GB'));
    }

    public function testOverrideExcludedLocalesCanBeCleared(): void
    {
        $loader = new TestableTranslationConfigLoader(new Filesystem(), ['excluded_locales' => []]);

        static::assertSame([], $loader->load()->excludedLocales);
    }

    public function testOverridePluginMappingReplacesList(): void
    {
        $loader = new TestableTranslationConfigLoader(new Filesystem(), [
            'plugin_mapping' => [['plugin' => 'FooPlugin', 'name' => 'BarSnippet']],
        ]);

        $config = $loader->load();

        $mapping = $config->pluginMapping->get('FooPlugin');
        static::assertInstanceOf(PluginMapping::class, $mapping);
        static::assertSame('BarSnippet', $mapping->snippetName);
        static::assertNull($config->pluginMapping->get('SwagPublisher'));
    }

    public function testOverrideCommunityTranslationsUrl(): void
    {
        $loader = new TestableTranslationConfigLoader(new Filesystem(), ['community_translations_url' => 'https://example.com/translate']);

        static::assertSame('https://example.com/translate', $loader->load()->communityTranslationsUrl?->__toString());
    }

    public function testOverrideDocumentationUrlSnippet(): void
    {
        $loader = new TestableTranslationConfigLoader(new Filesystem(), ['documentation_url_snippet_key' => 'my.custom.docs']);

        static::assertSame('my.custom.docs', $loader->load()->documentationUrlSnippetKey);
    }

    public function testOverrideCompletenessThreshold(): void
    {
        $loader = new TestableTranslationConfigLoader(new Filesystem(), ['completeness_threshold' => 75]);

        static::assertSame(75, $loader->load()->completenessThreshold);
    }

    public function testOverridePseudoLocalesReplacesList(): void
    {
        $loader = new TestableTranslationConfigLoader(new Filesystem(), ['pseudo_locales' => ['xx-XX']]);

        static::assertSame(['xx-XX'], $loader->load()->pseudoLocales);
    }

    public function testNullOverridesFallBackToConfigFile(): void
    {
        $loader = new TestableTranslationConfigLoader(new Filesystem(), [
            'repository_url' => null,
            'metadata_url' => null,
            'community_translations_url' => null,
            'documentation_url_snippet_key' => null,
            'completeness_threshold' => null,
            'plugins' => null,
            'excluded_locales' => null,
            'pseudo_locales' => null,
            'plugin_mapping' => null,
            'languages' => null,
        ]);

        $config = $loader->load();

        static::assertSame(
            'https://raw.githubusercontent.com/shopware/translations/main/translations',
            $config->repositoryUrl->__toString()
        );
        static::assertSame(['it-IT'], $config->excludedLocales);
    }

    public function testOverriddenInvalidUrlIsStillValidated(): void
    {
        $loader = new TestableTranslationConfigLoader(new Filesystem(), ['repository_url' => 'invalid_url']);

        $this->expectExceptionObject(SnippetException::invalidRepositoryUrl('invalid_url', new \Exception('"repository-url" must contain a schema and a host.')));
        $loader->load();
    }
}
