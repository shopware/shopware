<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final class ContentLayoutMapPropertyRequest
{
    use TargetsMutation;

    /**
     * @param array<string, mixed> $source
     */
    public function __construct(
        public readonly string $elementId,
        public readonly string $propertyKey,
        public readonly array $source,
        public readonly ?string $expectedVersion,
        public readonly ?string $draftId = null,
        public readonly ?string $expectedHeadRevisionId = null,
    ) {
    }
}
