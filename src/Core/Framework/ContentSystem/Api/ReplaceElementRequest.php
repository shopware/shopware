<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Envelope DTO for the replace-element mutation action.
 *
 * @internal
 */
#[Package('framework')]
final class ReplaceElementRequest
{
    /**
     * @param array<int|string, mixed> $layout
     */
    public function __construct(
        public readonly string $elementId,
        public readonly string $newType,
        #[Assert\Type('array')]
        public readonly array $layout = [],
        public readonly ?string $rootSource = null,
    ) {
    }
}
