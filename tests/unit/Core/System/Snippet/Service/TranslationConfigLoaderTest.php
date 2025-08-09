<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
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
    }

    public function testConfigFileSettings(): void
    {
        static::assertSame($this->translationConfigLoader->getParentConfigFilename(), 'translation.yaml');
        static::assertStringEndsWith('/../../Resources', $this->translationConfigLoader->getParentRelativeConfigurationPath());
    }

    public function testThrowsOnInvalidUrl(): void
    {
        $this->translationConfigLoader->setConfigFileName('translation_invalid_url.yaml');

        static::expectException(SnippetException::class);
        static::expectExceptionMessage('The repository URL "invalid_url" is invalid: The repository-url must contain a schema and a host.');
        $this->translationConfigLoader->load();
    }

    public function testThrowsOnBrokenUrl(): void
    {
        $this->translationConfigLoader->setConfigFileName('translation_broken_url.yaml');

        static::expectException(SnippetException::class);
        static::expectExceptionMessage('The repository URL "http://" is invalid: Unable to parse URI: http://');
        $this->translationConfigLoader->load();
    }

    public function testThrowsOnInvalidUrlType(): void
    {
        $this->translationConfigLoader->setConfigFileName('translation_non_string_url.yaml');

        static::expectException(SnippetException::class);
        static::expectExceptionMessage('The repository URL "4" is invalid: The repository-url in the translation config must be a string.');
        $this->translationConfigLoader->load();
    }

    public function testThrowsOnEmptyUrl(): void
    {
        $this->translationConfigLoader->setConfigFileName('translation_empty_string_url.yaml');

        static::expectException(SnippetException::class);
        static::expectExceptionMessage('The repository URL "" is invalid: The repository-url in the translation config must not be empty.');
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
        static::expectException(SnippetException::class);
        static::expectExceptionMessage('Translation configuration file does not exist: "non-existing-file".');
        $this->translationConfigLoader->load();
    }

    public function testThrowsOnEmptyConfigurationFile(): void
    {
        $this->translationConfigLoader->setConfigFileName('translation_empty.yaml');
        static::expectException(SnippetException::class);
        static::expectExceptionMessage('Translation configuration file exists, but is empty: "translation_empty.yaml".');
        $this->translationConfigLoader->load();
    }
}
