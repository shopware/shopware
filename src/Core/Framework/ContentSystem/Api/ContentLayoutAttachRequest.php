<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Envelope DTO for the persisted attach-element mutation action: splices a supplied element subtree into a stored
 * layout. Carries the optimistic-concurrency token (the layout's updatedAt, null for a never-updated layout);
 * the layout id is a path parameter.
 *
 * @internal
 */
#[Package('framework')]
final class ContentLayoutAttachRequest
{
    /**
     * @param array<string, mixed> $element
     */
    public function __construct(
        #[Assert\Type('array')]
        public readonly array $element,
        public readonly ?string $expectedVersion,
        public readonly ?string $parentElementId = null,
        public readonly ?string $slot = null,
        public readonly ?int $index = null,
    ) {
    }
}
