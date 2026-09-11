<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final class BindElementRequest
{
    /**
     * @param array<int|string, mixed> $layout
     */
    public function __construct(
        public readonly string $elementId,
        public readonly string $bindingSpecificationId,
        public readonly array $layout = [],
        public readonly ?string $rootSource = null,
    ) {
    }
}
