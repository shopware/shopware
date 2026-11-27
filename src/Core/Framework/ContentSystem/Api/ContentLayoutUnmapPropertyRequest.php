<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class ContentLayoutUnmapPropertyRequest
{
    use TargetsMutation;

    public function __construct(
        public string $elementId,
        public string $propertyKey,
        public ?string $expectedVersion,
        public ?string $draftId = null,
        public ?string $expectedHeadRevisionId = null,
    ) {
    }
}
