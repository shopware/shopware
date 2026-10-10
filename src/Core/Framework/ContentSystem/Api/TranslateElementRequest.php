<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Envelope DTO for the translate-element mutation action.
 *
 * @internal
 */
#[Package('framework')]
final class TranslateElementRequest
{
    /**
     * @param array<int|string, mixed> $layout
     * @param array<array-key, mixed> $values
     */
    public function __construct(
        public readonly string $elementId,
        public readonly array $layout = [],
        #[Assert\Count(min: 1)]
        public readonly array $values = [],
        public readonly ?string $rootSource = null,
    ) {
    }
}
