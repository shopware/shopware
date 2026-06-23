<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Envelope DTO for the persisted wrap-elements mutation action. Carries the optimistic-concurrency
 * token (the layout's updatedAt, null for a never-updated layout); the layout id is a path parameter.
 *
 * @internal
 */
#[Package('framework')]
final class ContentLayoutWrapElementsRequest
{
    /**
     * @param list<string> $elementIds
     */
    public function __construct(
        #[Assert\Type('array')]
        #[Assert\All([new Assert\Type('string'), new Assert\NotBlank()])]
        #[Assert\Unique]
        public readonly array $elementIds,
        public readonly string $containerType,
        public readonly ?string $expectedVersion,
        public readonly ?string $slot = null,
    ) {
    }
}
