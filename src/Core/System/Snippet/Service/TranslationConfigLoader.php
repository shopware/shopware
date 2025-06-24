<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Service;

use Shopware\Core\Framework\Log\Package;
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
        $path = realpath(self::TRANSLATION_CONFIG_DIR);

        if ($path === false) {
            throw SnippetException::translationConfigurationDirectoryDoesNotExist(self::TRANSLATION_CONFIG_DIR);
        }

        $path .= self::TRANSLATION_CONFIG_FILE;

        $content = file_get_contents($path);

        if ($content === false) {
            throw SnippetException::translationConfigurationFileDoesNotExist(self::TRANSLATION_CONFIG_FILE);
        }

        $config = Yaml::parse($content);

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

        return TranslationConfig::create(
            $url,
            $locales,
            $plugins,
            new LanguageCollection($languageData),
        );
    }
}
