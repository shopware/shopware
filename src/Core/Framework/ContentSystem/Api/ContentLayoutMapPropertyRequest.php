<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class ContentLayoutMapPropertyRequest
{
    use TargetsMutation;

    /**
     * @param array<string, mixed> $source
     */
    public function __construct(
        public string $elementId,
        public string $propertyKey,
        public array $source,
        public ?string $expectedVersion,
        public ?string $draftId = null,
        public ?string $expectedHeadRevisionId = null,
    ) {
    }
}
