<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\ContentSystem\Api\Validation\UpdateElementPropertiesNotEmpty;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Envelope DTO for the update-element-properties mutation action.
 *
 * @internal
 */
#[Package('framework')]
#[UpdateElementPropertiesNotEmpty]
final class UpdateElementPropertiesRequest
{
    /**
     * @param array<int|string, mixed> $layout
     * @param array<string, mixed> $values
     * @param list<string> $removeKeys
     */
    public function __construct(
        public readonly string $elementId,
        public readonly array $layout = [],
        public readonly array $values = [],
        #[Assert\All([new Assert\Type('string'), new Assert\NotBlank()])]
        #[Assert\Unique]
        public readonly array $removeKeys = [],
        public readonly ?string $rootSource = null,
    ) {
    }
}
