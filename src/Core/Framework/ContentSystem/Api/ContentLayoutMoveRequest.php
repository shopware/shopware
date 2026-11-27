<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\Log\Package;

/**
 * Envelope DTO for the persisted move-element mutation action. Carries the optimistic-concurrency
 * token (the layout's updatedAt, null for a never-updated layout); the layout id is a path parameter.
 *
 * @internal
 */
#[Package('framework')]
final readonly class ContentLayoutMoveRequest
{
    use TargetsMutation;

    public function __construct(
        public string $elementId,
        public ?string $expectedVersion,
        public ?string $newParentId = null,
        public ?string $newSlot = null,
        public ?int $index = null,
        public ?string $draftId = null,
        public ?string $expectedHeadRevisionId = null,
    ) {
    }
}
