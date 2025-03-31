<?php

declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Extension;

use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Translation\MessageCatalogueInterface;

/**
 * @extends Extension<array<string, string>>
 */
#[Package('discovery')]
final class StorefrontSnippetsExtension extends Extension
{
    public const NAME = 'storefront.snippets';

    /**
     * @internal shopware owns the __constructor, but the properties are public API
     *
     * @param array<string, string> $snippets
     * @param string[] $unusedThemes
     */
    public function __construct(
        public array $snippets,
        public readonly string $locale,
        public readonly MessageCatalogueInterface $catalog,
        public readonly string $snippetSetId,
        public readonly ?string $fallbackLocale,
        public readonly ?string $salesChannelId,
        public readonly array $unusedThemes
    ) {
    }
}
