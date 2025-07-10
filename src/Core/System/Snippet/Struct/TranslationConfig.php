<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('discovery')]
class TranslationConfig extends Struct
{
    /**
     * @param list<string> $locales
     * @param list<string> $plugins
     * @param array<string, string> $pluginMapping
     */
    private function __construct(
        public string $repositoryUrl,
        public array $locales,
        public array $plugins,
        public LanguageCollection $languages,
        public array $pluginMapping,
    ) {
    }

    /**
     * @param list<string> $locales
     * @param list<string> $plugins
     * @param array<string, string> $pluginMapping
     */
    public static function create(
        string $repositoryUrl,
        array $locales,
        array $plugins,
        LanguageCollection $languages,
        array $pluginMapping = [],
    ): self {
        return new self($repositoryUrl, $locales, $plugins, $languages, $pluginMapping);
    }
}
