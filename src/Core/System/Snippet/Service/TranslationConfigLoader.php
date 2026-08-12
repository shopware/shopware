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

    private const COMMUNITY_TRANSLATIONS_URL = 'community-translations-url';

    private const DOCUMENTATION_URL_SNIPPET_KEY = 'documentation-url-snippet-key';

    /**
     * @description Maps the snake_case keys of the `shopware.translation` config section to the dash-separated keys used in translation.yaml.
     */
    private const OVERRIDE_KEY_MAP = [
        'repository_url' => self::REPOSITORY_URL,
        'metadata_url' => self::METADATA_URL,
        'community_translations_url' => self::COMMUNITY_TRANSLATIONS_URL,
        'documentation_url_snippet_key' => self::DOCUMENTATION_URL_SNIPPET_KEY,
        'completeness_threshold' => 'completeness-threshold',
        'plugins' => 'plugins',
        'excluded_locales' => 'excluded-locales',
        'pseudo_locales' => 'pseudo-locales',
        'plugin_mapping' => 'plugin-mapping',
        'languages' => 'languages',
    ];

    /**
     * @param array<string, mixed> $translationConfig
     *
     * @description `shopware.translation` config section - keys left null fall back to translation.yaml
     */
    public function __construct(
        private readonly Filesystem $configReader,
        private readonly array $translationConfig = [],
    ) {
    }

    public function getDecorated(): AbstractTranslationConfigLoader
    {
        throw new DecorationPatternException(self::class);
    }

    public function load(): TranslationConfig
    {
        $config = $this->applyConfigOverrides($this->parseConfig());

        $repositoryUrl = $this->getUrlFromConfigByType(self::REPOSITORY_URL, $config);
        $metadataUrl = $this->getUrlFromConfigByType(self::METADATA_URL, $config);
        $communityTranslationsUrl = isset($config[self::COMMUNITY_TRANSLATIONS_URL])
            ? $this->getUrlFromConfigByType(self::COMMUNITY_TRANSLATIONS_URL, $config)
            : null;
        // Snippet key (not a URL): the actual documentation link is resolved per admin language via this snippet.
        $documentationUrlSnippetKey = isset($config[self::DOCUMENTATION_URL_SNIPPET_KEY])
            ? (string) $config[self::DOCUMENTATION_URL_SNIPPET_KEY]
            : null;

        /** @var list<string> $plugins */
        $plugins = $config['plugins'];
        \assert(\is_array($plugins), 'The plugins in the translation config must be an array.');

        $languages = $config['languages'] ?? [];
        $excludedLocales = $config['excluded-locales'] ?? [];
        $pseudoLocales = $config['pseudo-locales'] ?? [];
        $completenessThreshold = (int) ($config['completeness-threshold'] ?? 90);

        $locales = [];
        $languageData = [];

        foreach ($languages as $language) {
            $locales[] = $language['locale'];
            $languageData[] = new Language($language['locale'], $language['name']);
        }

        $pluginMapping = $this->getPluginMapping($config['plugin-mapping'] ?? []);

        return new TranslationConfig(
            $repositoryUrl,
            $locales,
            $plugins,
            new LanguageCollection($languageData),
            $pluginMapping,
            $metadataUrl,
            $excludedLocales,
            $communityTranslationsUrl,
            $documentationUrlSnippetKey,
            $pseudoLocales,
            $completenessThreshold,
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
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function applyConfigOverrides(array $config): array
    {
        foreach (self::OVERRIDE_KEY_MAP as $overrideKey => $configKey) {
            if (($this->translationConfig[$overrideKey] ?? null) !== null) {
                $config[$configKey] = $this->translationConfig[$overrideKey];
            }
        }

        return $config;
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

        if (\trim($content) === '') {
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
        if (\trim($urlString) === '') {
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

        if ($url->getScheme() === '' || $url->getHost() === '') {
            throw SnippetException::invalidRepositoryUrl(
                $urlString,
                new MalformedUriException(\sprintf('"%s" must contain a schema and a host.', $type))
            );
        }

        return $url;
    }
}
