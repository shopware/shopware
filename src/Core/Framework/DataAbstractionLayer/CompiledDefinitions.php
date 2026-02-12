<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class CompiledDefinitions
{
    /**
     * @param list<MappingMetadata> $mappings
     */
    public function __construct(
        public ?EntityMetadata $entity,
        public array $mappings = [],
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->entity === null && $this->mappings === [];
    }
}
