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
final readonly class ContentLayoutUpdateElementPropertiesRequest
{
    use TargetsMutation;

    /**
     * @param array<array-key, mixed> $values
     * @param list<string> $removeKeys
     */
    public function __construct(
        public string $elementId,
        public ?string $expectedVersion,
        public array $values = [],
        #[Assert\All([new Assert\Type('string'), new Assert\NotBlank()])]
        #[Assert\Unique]
        public array $removeKeys = [],
        public ?string $draftId = null,
        public ?string $expectedHeadRevisionId = null,
    ) {
    }
}
