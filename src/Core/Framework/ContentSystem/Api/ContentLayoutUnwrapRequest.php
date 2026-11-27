<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\Log\Package;

/**
 * Envelope DTO for the persisted unwrap-element mutation action. Carries the optimistic-concurrency
 * token (the layout's updatedAt, null for a never-updated layout); the layout id is a path parameter.
 *
 * @internal
 */
#[Package('framework')]
final readonly class ContentLayoutUnwrapRequest
{
    use TargetsMutation;

    public function __construct(
        public string $containerElementId,
        public ?string $expectedVersion,
        public ?string $draftId = null,
        public ?string $expectedHeadRevisionId = null,
    ) {
    }
}
