<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Envelope DTO for the resolve-and-diagnose action. The raw layout tree stays undecoded so
 * ContentElementFieldSerializer::decodeElement() remains the single decode path. Optional source fields
 * (entityType or section) enable the binding-resolvability branch; absent both, only well-formedness is checked.
 *
 * @internal
 */
#[Package('framework')]
class ContentLayoutDiagnoseRequest
{
    /**
     * @param array<int|string, mixed> $layout
     */
    public function __construct(
        #[Assert\Type('array')]
        public readonly array $layout = [],
        public readonly ?string $entityType = null,
        public readonly ?string $section = null,
    ) {
    }
}
