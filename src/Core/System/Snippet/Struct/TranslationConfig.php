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
     */
    private function __construct(
        public string $repositoryUrl,
        public array $locales,
        public array $plugins,
        public LanguageCollection $languages,
    ) {
    }

    /**
     * @param list<string> $locales
     * @param list<string> $plugins
     */
    public static function create(
        string $repositoryUrl,
        array $locales,
        array $plugins,
        LanguageCollection $languages
    ): self {
        return new self($repositoryUrl, $locales, $plugins, $languages);
    }
}
