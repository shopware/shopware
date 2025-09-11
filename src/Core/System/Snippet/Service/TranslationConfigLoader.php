<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Service;

use GuzzleHttp\Psr7\Exception\MalformedUriException;
use GuzzleHttp\Psr7\Uri;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
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
class TranslationConfigLoader extends AbstractTranslationConfigLoader
{
    private const REPOSITORY_URL = 'repository-url';

    private const METADATA_URL = 'metadata-url';

    public function __construct(
        private readonly Filesystem $configReader,
    ) {
    }

    public function getDecorated(): AbstractTranslationConfigLoader
    {
        throw new DecorationPatternException(self::class);
    }

    public function load(): TranslationConfig
    {
        $config = $this->parseConfig();

        $repositoryUrl = $this->getUrlFromConfigByType(self::REPOSITORY_URL, $config);
        $metadataUrl = $this->getUrlFromConfigByType(self::METADATA_URL, $config);

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

        return new TranslationConfig(
            $repositoryUrl,
            $locales,
            $plugins,
            new LanguageCollection($languageData),
            $pluginMapping,
            $metadataUrl
        );
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

    /**
     * @param array<string, mixed> $config
     */
    private function getUrlFromConfigByType(string $type, array $config): Uri
    {
        $url = $config[$type];

        if (!\is_string($url)) {
            $exception = new \InvalidArgumentException(\sprintf('"%s" in the translation config must be a string.', $type));

            try {
                $encodedUrl = json_encode($url, \JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                $encodedUrl = \sprintf('Unable to convert %s to string.', $type);
                $exception = $e;
            }

            throw SnippetException::invalidRepositoryUrl($encodedUrl, $exception);
        }

        return $this->getValidatedUrl($url, $type);
    }

    private function getValidatedUrl(string $urlString, string $type): Uri
    {
        if (\mb_strlen(\trim($urlString)) < 1) {
            throw SnippetException::invalidRepositoryUrl(
                $urlString,
                new \InvalidArgumentException(\sprintf('"%s" in the translation config must not be empty.', $type))
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
                new MalformedUriException(\sprintf('"%s" must contain a schema and a host.', $type))
            );
        }

        return $url;
    }
}
