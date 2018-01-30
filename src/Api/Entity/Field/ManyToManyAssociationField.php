<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Field;

use Shopware\Api\Entity\EntityDefinition;

class ManyToManyAssociationField extends SubresourceField implements AssociationInterface
{
    use AssociationTrait;

    /**
     * @var string
     */
    private $referenceDefinition;

    /**
     * @var string
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
    private $structIdMappingProperty;

    /**
     * @var string
     */
    private $sourceColumn;
    /**
     * @var string
     */
    private $referenceColumn;

    public function __construct(
        string $propertyName,
        string $referenceClass,
        string $mappingDefinition,
        bool $loadInBasic,
        string $mappingLocalColumn,
        string $mappingReferenceColumn,
        string $structIdMappingProperty,
        string $sourceColumn = 'id',
        string $referenceColumn = 'id'
    ) {
        parent::__construct($propertyName, $mappingDefinition);
        $this->referenceDefinition = $referenceClass;
        $this->loadInBasic = $loadInBasic;
        $this->mappingDefinition = $mappingDefinition;
        $this->mappingLocalColumn = $mappingLocalColumn;
        $this->mappingReferenceColumn = $mappingReferenceColumn;
        $this->structIdMappingProperty = $structIdMappingProperty;
        $this->sourceColumn = $sourceColumn;
        $this->referenceColumn = $referenceColumn;
    }

    /**
     * @return string|EntityDefinition
     */
    public function getReferenceDefinition(): string
    {
        return $this->referenceDefinition;
    }

    /**
     * @return string|EntityDefinition
     */
    public function getMappingDefinition(): string
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

    public function getStructIdMappingProperty(): string
    {
        return $this->structIdMappingProperty;
    }

    public function getSourceColumn(): string
    {
        return $this->sourceColumn;
    }

    public function getReferenceColumn(): string
    {
        return $this->referenceColumn;
    }
}
