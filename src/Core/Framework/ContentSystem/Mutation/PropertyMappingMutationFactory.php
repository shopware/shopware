<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation;

use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\ContentSystem\Mapping\StoredMappingInspector;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\MapProperty;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\UnmapProperty;
use Shopware\Core\Framework\Log\Package;

/**
 * Builds property-mapping mutations with server-owned catalogue and type metadata.
 *
 * @internal
 */
#[Package('framework')]
final readonly class PropertyMappingMutationFactory
{
    public function __construct(
        private AbstractContentSystemElementTypeRegistry $typeRegistry,
        private StoredMappingInspector $mappingInspector,
    ) {
    }

    public function map(string $elementId, string $propertyKey, MappingSourceReference $source): MapProperty
    {
        return new MapProperty(
            $this->typeRegistry,
            $this->mappingInspector,
            $elementId,
            $propertyKey,
            $source,
        );
    }

    public function unmap(string $elementId, string $propertyKey): UnmapProperty
    {
        return new UnmapProperty($elementId, $propertyKey);
    }
}
