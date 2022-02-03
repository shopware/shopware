<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\ManyToManyAssociationFieldResolver;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\ManyToManyAssociationFieldSerializer;

class ManyToManyAssociationField extends AssociationField
{
    /**
     * @var EntityDefinition
     */
    private $mappingDefinition;

    /**
     * @var string
     */
    private $mappingLocalColumn;

    /**
     * @var string
     */
    private $mappingReferenceColumn;

    /**
     * @var string
     */
    private $sourceColumn;

    /**
     * @var string
     */
    private $toManyDefinitionClass;

    /**
     * @var EntityDefinition
     */
    private $toManyDefinition;

    private ?string $mappingReferenceEntity;

    public function __construct(
        string $propertyName,
        string $referenceDefinition,
        string $mappingDefinition,
        string $mappingLocalColumn,
        string $mappingReferenceColumn,
        string $sourceColumn = 'id',
        string $referenceField = 'id',
        ?string $mappingReferenceEntity = null,
        ?string $referenceEntity = null
    ) {
        parent::__construct($propertyName);
        $this->toManyDefinitionClass = $referenceDefinition;
        $this->referenceClass = $mappingDefinition;
        $this->mappingLocalColumn = $mappingLocalColumn;
        $this->mappingReferenceColumn = $mappingReferenceColumn;
        $this->sourceColumn = $sourceColumn;
        $this->referenceField = $referenceField;

        $this->referenceEntity = $mappingReferenceEntity;
        $this->mappingReferenceEntity = $referenceEntity;
    }

    public function compile(DefinitionInstanceRegistry $registry): void
    {
        if ($this->mappingDefinition !== null) {
            return;
        }

        parent::compile($registry);

        if ($this->mappingReferenceEntity !== null) {
            $this->toManyDefinition = $registry->getByEntityName($this->mappingReferenceEntity);
            $this->toManyDefinitionClass = $this->toManyDefinition->getClass();
        } else {
            $this->toManyDefinition = $registry->get($this->toManyDefinitionClass);
            $this->mappingReferenceEntity = $this->toManyDefinition->getEntityName();
        }

        $this->mappingDefinition = $this->referenceDefinition;
    }

    public function getToManyReferenceDefinition(): EntityDefinition
    {
        return $this->toManyDefinition;
    }

    public function getMappingDefinition(): EntityDefinition
    {
        return $this->mappingDefinition;
    }

    public function getMappingLocalColumn(): string
    {
        return $this->mappingLocalColumn;
    }

    public function getMappingReferenceColumn(): string
    {
        return $this->mappingReferenceColumn;
    }

    public function getLocalField(): string
    {
        return $this->sourceColumn;
    }

    protected function getSerializerClass(): string
    {
        return ManyToManyAssociationFieldSerializer::class;
    }

    protected function getResolverClass(): ?string
    {
        return ManyToManyAssociationFieldResolver::class;
    }
}
