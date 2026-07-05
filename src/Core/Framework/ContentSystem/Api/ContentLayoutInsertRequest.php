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
final class ContentLayoutInsertRequest
{
    public function __construct(
        public readonly string $type,
        public readonly ?string $expectedVersion,
        public readonly ?string $parentElementId = null,
        public readonly ?string $slot = null,
        public readonly ?int $index = null,
        public readonly ?string $bindingSpecificationId = null,
    ) {
    }
}
