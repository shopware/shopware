<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Struct;

use GuzzleHttp\Psr7\Uri;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\Snippet\DataTransfer\Language\LanguageCollection;
use Shopware\Core\System\Snippet\DataTransfer\PluginMapping\PluginMappingCollection;
use Shopware\Core\System\Snippet\SnippetException;

#[Package('discovery')]
class TranslationConfig extends Struct
{
    /**
     * @param list<string> $locales
     * @param list<string> $plugins
     * @param list<string> $excludedLocales
     *
     * @internal
     */
    public function __construct(
        public readonly Uri $repositoryUrl,
        public readonly array $locales,
        public readonly array $plugins,
        public readonly LanguageCollection $languages,
        public readonly PluginMappingCollection $pluginMapping,
        public readonly Uri $metadataUrl,
        public readonly array $excludedLocales,
    ) {
    }

    public function getMappedPluginName(Plugin $plugin): string
    {
        $pluginName = $plugin->getName();

        return $this->pluginMapping->get($pluginName)->snippetName ?? $pluginName;
    }

    /**
     * @param list<string> $locales
     */
    public function validateLocales(array $locales): void
    {
        if ($locales === []) {
            throw SnippetException::noLocalesArgumentProvided();
        }

        $invalid = array_values(array_diff($locales, $this->locales));
        if ($invalid === []) {
            return;
        }

        throw SnippetException::invalidLocalesProvided(
            implode(', ', $invalid),
            implode(', ', $this->locales)
        );
    }
}
