<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[Package('framework')]
final readonly class MappingMetadata
{
    /**
     * @param non-empty-string $entityName
     * @param list<FieldMetadata> $fields
     * @param string $source Source entity name
     * @param string $reference Reference entity name
     */
    public function __construct(
        public string $entityName,
        public array $fields,
        public string $source,
        public string $reference,
    ) {
    }

    public function toDefinition(): Definition
    {
        return new Definition(self::class, [
            $this->entityName,
            array_map(fn (FieldMetadata $f) => $f->toDefinition(), $this->fields),
            $this->source,
            $this->reference,
        ]);
    }
}
