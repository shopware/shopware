<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\ContentSystem\Api\Validation\UpdateElementPropertiesNotEmpty;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Envelope DTO for the persisted update-element-properties mutation action. Carries the
 * optimistic-concurrency token (the layout's updatedAt, null for a never-updated layout); the layout id is
 * a path parameter.
 *
 * @internal
 */
#[Package('framework')]
#[UpdateElementPropertiesNotEmpty]
final class ContentLayoutUpdateElementPropertiesRequest
{
    /**
     * @param array<string, mixed> $values
     * @param list<string> $removeKeys
     */
    public function __construct(
        public readonly string $elementId,
        public readonly ?string $expectedVersion,
        #[Assert\Type('array')]
        public readonly array $values = [],
        #[Assert\Type('array')]
        #[Assert\All([new Assert\Type('string'), new Assert\NotBlank()])]
        #[Assert\Unique]
        public readonly array $removeKeys = [],
    ) {
    }
}
