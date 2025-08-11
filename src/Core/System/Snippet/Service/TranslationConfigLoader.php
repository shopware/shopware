<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Service;

use GuzzleHttp\Psr7\Exception\MalformedUriException;
use GuzzleHttp\Psr7\Uri;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\DataTransfer\Language\Language;
use Shopware\Core\System\Snippet\DataTransfer\Language\LanguageCollection;
use Shopware\Core\System\Snippet\DataTransfer\PluginMapping\PluginMapping;
use Shopware\Core\System\Snippet\DataTransfer\PluginMapping\PluginMappingCollection;
use Shopware\Core\System\Snippet\SnippetException;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 */
#[Package('discovery')]
class TranslationConfigLoader
{
    public function __construct(
        private readonly Filesystem $configReader,
    ) {
    }

    public function load(): TranslationConfig
    {
        $config = $this->parseConfig();

        $urlString = $config['repository-url'];

        if (!\is_string($urlString)) {
            $exception = new \InvalidArgumentException('The repository-url in the translation config must be a string.');
            try {
                $encodedUrl = json_encode($urlString, \JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                $encodedUrl = 'Unable to convert repository-url to string.';
                $exception = $e;
            }

            throw SnippetException::invalidRepositoryUrl($encodedUrl, $exception);
        }
        if (\mb_strlen(\trim($urlString)) < 1) {
            throw SnippetException::invalidRepositoryUrl(
                $urlString,
                new \InvalidArgumentException('The repository-url in the translation config must not be empty.')
            );
        }

        try {
            $url = new Uri($urlString);
        } catch (MalformedUriException $e) {
            throw SnippetException::invalidRepositoryUrl($urlString, $e);
        }

        if (empty($url->getScheme()) || empty($url->getHost())) {
            throw SnippetException::invalidRepositoryUrl(
                $urlString,
                new MalformedUriException('The repository-url must contain a schema and a host.')
            );
        }

        /** @var list<string> $locales */
        $locales = $config['locales'];
        \assert(\is_array($locales), 'The locales in the translation config must be an array.');

        /** @var list<string> $plugins */
        $plugins = $config['plugins'];
        \assert(\is_array($plugins), 'The plugins in the translation config must be an array.');

        $languages = $config['languages'] ?? [];

        $languageData = [];
        foreach ($languages as $language) {
            $languageData[] = new Language($language['locale'], $language['name']);
        }

        $pluginMapping = $this->getPluginMapping($config['plugin-mapping'] ?? []);

        return new TranslationConfig($url, $locales, $plugins, new LanguageCollection($languageData), $pluginMapping);
    }

    protected function getRelativeConfigurationPath(): string
    {
        return __DIR__ . '/../../Resources';
    }

    protected function getConfigFilename(): string
    {
        return 'translation.yaml';
    }

    /**
     * @return array<string, mixed>
     */
    private function parseConfig(): array
    {
        $configPath = \realpath($this->getRelativeConfigurationPath());

        if ($configPath === false) {
            throw SnippetException::translationConfigurationDirectoryDoesNotExist($this->getRelativeConfigurationPath());
        }

        $configFilePath = Path::join($configPath, $this->getConfigFilename());
        try {
            $content = $this->configReader->readFile($configFilePath);
        } catch (IOException $e) {
            throw SnippetException::translationConfigurationFileDoesNotExist($this->getConfigFilename(), $e);
        }

        if (empty(\trim($content))) {
            throw SnippetException::translationConfigurationFileIsEmpty($this->getConfigFilename());
        }

        return Yaml::parse($content);
    }

    /**
     * @param list<array{plugin: string, name: string}> $pluginMappingsConfig
     */
    private function getPluginMapping(array $pluginMappingsConfig): PluginMappingCollection
    {
        $pluginMappings = new PluginMappingCollection();

        foreach ($pluginMappingsConfig as $pluginMappingConfig) {
            $pluginMappings->set(
                $pluginMappingConfig['plugin'],
                new PluginMapping(
                    $pluginMappingConfig['plugin'],
                    $pluginMappingConfig['name']
                )
            );
        }

        return $pluginMappings;
    }
}
