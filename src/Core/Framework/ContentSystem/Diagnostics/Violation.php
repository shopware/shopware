<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Diagnostics;

use Shopware\Core\Framework\ContentSystem\Resolution\ResolutionCandidate;
use Shopware\Core\Framework\Log\Package;

/**
 * A single defect found in a layout, addressed by element id plus an optional property/context key.
 * Scope and severity are derived from the {@see ViolationCode}.
 *
 * @internal
 */
#[Package('framework')]
final readonly class Violation
{
    /**
     * @param list<ResolutionCandidate> $candidates
     */
    public function __construct(
        public ViolationCode $code,
        public string $elementId,
        public ?string $key,
        public string $message,
        public array $candidates = [],
    ) {
    }

    /**
     * The tree-global duplicate-id defect, minted here so its code and message are stated once: the write
     * boundary and the diagnostics report both raise it, and a wording that drifted between them would give
     * one defect two descriptions.
     */
    public static function duplicateElementId(string $id): self
    {
        return new self(
            ViolationCode::DuplicateElementId,
            $id,
            null,
            \sprintf('Element id "%s" is not unique across the layout.', $id),
        );
    }

    public function scope(): ViolationScope
    {
        return $this->code->scope();
    }

    public function severity(): ViolationSeverity
    {
        return $this->code->severity();
    }
}
