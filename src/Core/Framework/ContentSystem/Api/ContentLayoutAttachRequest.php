<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\Log\Package;

/**
 * Envelope DTO for the persisted attach-element mutation action: splices a supplied element subtree into a stored
 * layout. Carries the optimistic-concurrency token (the layout's updatedAt, null for a never-updated layout);
 * the layout id is a path parameter.
 *
 * @internal
 */
#[Package('framework')]
final readonly class ContentLayoutAttachRequest
{
    use TargetsMutation;

    /**
     * @param array<string, mixed> $element
     */
    public function __construct(
        public array $element,
        public ?string $expectedVersion,
        public ?string $parentElementId = null,
        public ?string $slot = null,
        public ?int $index = null,
        public ?string $draftId = null,
        public ?string $expectedHeadRevisionId = null,
    ) {
    }
}
