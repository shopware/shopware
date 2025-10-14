<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Routing\IdResolution;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
final readonly class ResolutionConfig
{
    /**
     * @param array<string, mixed> $constraints e.g., ['stock' => ['gte' => 100]]
     */
    public function __construct(
        public string $entity,
        public string $matchField,
        public array $constraints = []
    ) {
    }
}
