<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Envelope DTO for the attach-element mutation action: splices a supplied element subtree into the draft.
 *
 * @internal
 */
#[Package('framework')]
final class AttachElementRequest
{
    /**
     * @param array<string, mixed> $element
     * @param array<int|string, mixed> $layout
     */
    public function __construct(
        #[Assert\Type('array')]
        public readonly array $element,
        #[Assert\Type('array')]
        public readonly array $layout = [],
        public readonly ?string $parentElementId = null,
        public readonly ?string $slot = null,
        public readonly ?int $index = null,
        public readonly ?string $rootSource = null,
    ) {
    }
}
