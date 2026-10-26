<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\Log\Package;

/**
 * Envelope DTO for the resolve-and-diagnose action; the raw layout tree stays undecoded so DraftLayoutDecoder
 * remains the single decode path. The optional rootSource enables the resolvability branch; absent it, only
 * well-formedness is checked.
 *
 * @internal
 */
#[Package('framework')]
final class ContentDiagnoseRequest
{
    /**
     * @param array<int|string, mixed> $layout
     */
    public function __construct(
        public readonly array $layout = [],
        public readonly ?string $rootSource = null,
    ) {
    }
}
