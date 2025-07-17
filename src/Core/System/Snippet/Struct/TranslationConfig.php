<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('discovery')]
class TranslationConfig extends Struct
{
    public string $repositoryUrl;

    /**
     * @var list<string>
     */
    public array $locales;

    /**
     * @var list<string>
     */
    public array $plugins;

    public LanguageCollection $languages;

    /**
     * @var array<string, string>
     */
    public array $pluginMapping;

    /**
     * @internal
     *
     * @param list<string> $locales
     * @param list<string> $plugins
     * @param array<string, string> $pluginMapping
     */
    public function __construct(
        string $repositoryUrl,
        array $locales,
        array $plugins,
        LanguageCollection $languages,
        array $pluginMapping
    ) {
        $this->repositoryUrl = $repositoryUrl;
        $this->locales = $locales;
        $this->plugins = $plugins;
        $this->languages = $languages;
        $this->pluginMapping = $pluginMapping;
    }
}
