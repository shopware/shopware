<?php
declare(strict_types=1);

namespace Shopware\Administration\Extension;

use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends Extension<SnippetsArray>
 *
 * @phpstan-type SnippetsArray array<string, string|SnippetsArray>
 */
#[Package('discovery')]
final class SnippetExtension extends Extension
{
    public const NAME = 'administration.snippets';

    /**
     * @param SnippetsArray $snippets
     *
     * @internal shopware owns the __constructor, but the properties are public API
     */
    public function __construct(
        public array $snippets,
        public readonly string $locale,
    ) {
    }
}
