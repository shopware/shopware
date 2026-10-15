<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping\Inline;

use Shopware\Core\Framework\Log\Package;

/**
 * Authored inline-token spans captured once during tree preparation and carried through rendering.
 *
 * @internal
 */
#[Package('framework')]
final readonly class InlineMappingMetadata
{
    /**
     * @param array<string, array<string, list<InlineMappingToken>>> $tokens element id => property key => tokens
     */
    public function __construct(
        public array $tokens = [],
    ) {
    }

    /**
     * @return list<InlineMappingToken>
     */
    public function tokensFor(string $elementId, string $propertyKey): array
    {
        return $this->tokens[$elementId][$propertyKey] ?? [];
    }
}
