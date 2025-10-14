<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Routing\IdResolution\Parameter;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
final readonly class EntityResolution
{
    /**
     * @param array<string, mixed> $constraints
     */
    public function __construct(
        public string $entityType,
        public string $matchField = 'id',
        public array $constraints = []
    ) {
    }
}
