<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final class ContentLayoutUnmapPropertyRequest
{
    use TargetsMutation;

    public function __construct(
        public readonly string $elementId,
        public readonly string $propertyKey,
        public readonly ?string $expectedVersion,
        public readonly ?string $draftId = null,
        public readonly ?string $expectedHeadRevisionId = null,
    ) {
    }
}
