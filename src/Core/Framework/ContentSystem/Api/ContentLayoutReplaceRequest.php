<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\Log\Package;

/**
 * Envelope DTO for the persisted replace-element mutation action. Carries the optimistic-concurrency
 * token (the layout's updatedAt, null for a never-updated layout); the layout id is a path parameter.
 *
 * @internal
 */
#[Package('framework')]
final class ContentLayoutReplaceRequest
{
    public function __construct(
        public readonly string $elementId,
        public readonly string $newType,
        public readonly ?string $expectedVersion,
    ) {
    }
}
