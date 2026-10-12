<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Envelope DTO for the persisted translate-element mutation action. Carries the optimistic-concurrency token (the
 * layout's updatedAt, null for a never-updated layout); the layout id is a path parameter.
 *
 * @internal
 */
#[Package('framework')]
final class ContentLayoutTranslateElementRequest
{
    /**
     * @param array<array-key, mixed> $values
     */
    public function __construct(
        public readonly string $elementId,
        public readonly ?string $expectedVersion,
        #[Assert\Count(min: 1)]
        public readonly array $values = [],
    ) {
    }
}
