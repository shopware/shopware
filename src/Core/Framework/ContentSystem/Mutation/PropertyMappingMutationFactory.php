<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation;

use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingTypeCompatibility;
use Shopware\Core\Framework\ContentSystem\Mapping\Registry\AbstractContentSystemMappingCandidateRegistry;
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
        private AbstractContentSystemMappingCandidateRegistry $candidateRegistry,
        private MappingTypeCompatibility $compatibility,
    ) {
    }

    public function map(string $rootSource, string $elementId, string $propertyKey, string $sourcePath): MapProperty
    {
        return new MapProperty(
            $this->typeRegistry,
            $this->candidateRegistry,
            $this->compatibility,
            $rootSource,
            $elementId,
            $propertyKey,
            $sourcePath,
        );
    }

    public function unmap(string $elementId, string $propertyKey): UnmapProperty
    {
        return new UnmapProperty($elementId, $propertyKey);
    }
}
