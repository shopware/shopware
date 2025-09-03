<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Struct;

use GuzzleHttp\Psr7\Uri;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\Snippet\DataTransfer\Language\LanguageCollection;
use Shopware\Core\System\Snippet\DataTransfer\PluginMapping\PluginMappingCollection;

#[Package('discovery')]
class TranslationConfig extends Struct
{
    /**
     * @param list<string> $locales
     * @param list<string> $plugins
     *
     * @internal
     */
    public function __construct(
        public readonly Uri $repositoryUrl,
        public readonly array $locales,
        public readonly array $plugins,
        public readonly LanguageCollection $languages,
        public readonly PluginMappingCollection $pluginMapping
    ) {
    }

    public function getMappedPluginName(Plugin $plugin): string
    {
        $pluginName = $plugin->getName();

        return $this->pluginMapping->get($pluginName)->snippetName ?? $pluginName;
    }
}
