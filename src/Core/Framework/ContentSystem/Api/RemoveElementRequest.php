<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\Log\Package;

/**
 * Envelope DTO for the remove-element mutation action.
 *
 * @internal
 */
#[Package('framework')]
final class RemoveElementRequest
{
    /**
     * @param array<int|string, mixed> $layout
     */
    public function __construct(
        public readonly string $elementId,
        public readonly array $layout = [],
        public readonly ?string $rootSource = null,
    ) {
    }
}
