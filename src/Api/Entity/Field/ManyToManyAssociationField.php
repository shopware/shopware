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
    private $structUuidMappingProperty;

    public function __construct(
        string $propertyName,
        string $referenceClass,
        string $mappingDefinition,
        bool $loadInBasic,
        string $mappingLocalColumn,
        string $mappingReferenceColumn,
        string $structUuidMappingProperty
    ) {
        parent::__construct($propertyName, $mappingDefinition);
        $this->referenceDefinition = $referenceClass;
        $this->loadInBasic = $loadInBasic;
        $this->mappingDefinition = $mappingDefinition;
        $this->mappingLocalColumn = $mappingLocalColumn;
        $this->mappingReferenceColumn = $mappingReferenceColumn;
        $this->structUuidMappingProperty = $structUuidMappingProperty;
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

    public function getStructUuidMappingProperty(): string
    {
        return $this->structUuidMappingProperty;
    }
}
