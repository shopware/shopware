<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('discovery')]
class TranslationConfig extends Struct
{
    /**
     * @internal
     *
     * @param list<string> $locales
     * @param list<string> $plugins
     * @param array<string, string> $pluginMapping
     */
    public function __construct(
        public readonly string $repositoryUrl,
        public readonly array $locales,
        public readonly array $plugins,
        public readonly LanguageCollection $languages,
        public readonly array $pluginMapping
    ) {
    }
}
