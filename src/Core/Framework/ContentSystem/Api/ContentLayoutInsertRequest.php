<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\Log\Package;

/**
 * Envelope DTO for the persisted insert-element mutation action. Carries the optimistic-concurrency
 * token (the layout's updatedAt, null for a never-updated layout); the layout id is a path parameter.
 *
 * @internal
 */
#[Package('framework')]
final readonly class ContentLayoutInsertRequest
{
    use TargetsMutation;

    public function __construct(
        public string $type,
        public ?string $expectedVersion,
        public ?string $parentElementId = null,
        public ?string $slot = null,
        public ?int $index = null,
        public ?string $bindingSpecificationId = null,
        public ?string $draftId = null,
        public ?string $expectedHeadRevisionId = null,
    ) {
    }
}
