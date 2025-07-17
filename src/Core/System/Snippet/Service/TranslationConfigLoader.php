<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Service;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\System\Snippet\SnippetException;
use Shopware\Core\System\Snippet\Struct\Language;
use Shopware\Core\System\Snippet\Struct\LanguageCollection;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 */
#[Package('discovery')]
class TranslationConfigLoader
{
    private const TRANSLATION_CONFIG_DIR = __DIR__ . '/../../Resources';

    private const TRANSLATION_CONFIG_FILE = '/translation.yaml';

    public static function load(): TranslationConfig
    {
        $config = self::parseConfig();

        $url = $config['repository-url'];
        \assert(\is_string($url), 'The repository-url in the translation config must be a string.');

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

        $pluginMapping = self::getPluginMapping($config);

        return new TranslationConfig($url, $locales, $plugins, new LanguageCollection($languageData), $pluginMapping);
    }

    public static function getMappedPluginName(Plugin $plugin): string
    {
        $config = self::parseConfig();
        $mapping = self::getPluginMapping($config);

        $name = $plugin->getName();

        return $mapping[$name] ?? $name;
    }

    /**
     * @return array<string, mixed>
     */
    private static function parseConfig(): array
    {
        $path = realpath(self::TRANSLATION_CONFIG_DIR);

        if ($path === false) {
            throw SnippetException::translationConfigurationDirectoryDoesNotExist(self::TRANSLATION_CONFIG_DIR);
        }

        $path .= self::TRANSLATION_CONFIG_FILE;
        $content = file_get_contents($path);

        if ($content === false) {
            throw SnippetException::translationConfigurationFileDoesNotExist(self::TRANSLATION_CONFIG_FILE);
        }

        return Yaml::parse($content);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, string>
     */
    private static function getPluginMapping(array $config): array
    {
        $result = [];
        $mapping = $config['plugin-mapping'] ?? [];

        foreach ($mapping as $data) {
            $plugin = $data['plugin'];
            $name = $data['name'];

            $result[$plugin] = $name;
        }

        return $result;
    }
}
