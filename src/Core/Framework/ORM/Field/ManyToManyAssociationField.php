<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Field;

use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\Write\DataStack\KeyValuePair;
use Shopware\Framework\ORM\Write\EntityExistence;

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
        string $sourceColumn = 'id',
        string $referenceColumn = 'id'
    ) {
        parent::__construct($propertyName, $mappingDefinition);
        $this->referenceDefinition = $referenceClass;
        $this->loadInBasic = $loadInBasic;
        $this->mappingDefinition = $mappingDefinition;
        $this->mappingLocalColumn = $mappingLocalColumn;
        $this->mappingReferenceColumn = $mappingReferenceColumn;
        $this->sourceColumn = $sourceColumn;
        $this->referenceColumn = $referenceColumn;
    }

    public function __invoke(EntityExistence $existence, KeyValuePair $data): \Generator
    {
        $key = $data->getKey();
        $value = $data->getValue();

        if (!is_array($value)) {
            throw new MalformatDataException($this->path . '/' . $key, 'Value must be an array.');
        }

        $isNumeric = array_keys($value) === range(0, count($value) - 1);

        $mappingAssociation = $this->getMappingAssociation();

        foreach ($value as $keyValue => $subresources) {
            $mapped = $subresources;
            if ($mappingAssociation) {
                /** @var ManyToOneAssociationField $mappingAssociation */
                $mapped = [$mappingAssociation->getPropertyName() => $subresources];
            }

            if (!is_array($mapped)) {
                throw new MalformatDataException($this->path . '/' . $key, 'Value must be an array.');
            }

            if ($this->possibleKey && !$isNumeric) {
                $mapped[$this->possibleKey] = $keyValue;
            }

            $this->writeResource->extract(
                $mapped,
                $this->referenceClass,
                $this->exceptionStack,
                $this->commandQueue,
                $this->writeContext,
                $this->fieldExtenderCollection,
                $this->path . '/' . $key . '/' . $keyValue
            );
        }

        return;
        yield __CLASS__ => __METHOD__;
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

    public function getLocalField(): string
    {
        return $this->sourceColumn;
    }

    public function getReferenceField(): string
    {
        return $this->referenceColumn;
    }

    private function getMappingAssociation(): ?AssociationInterface
    {
        $associations = $this->getReferenceClass()::getFields()->filterInstance(AssociationInterface::class);

        /** @var ManyToOneAssociationField $association */
        foreach ($associations as $association) {
            if ($association->getStorageName() === $this->getMappingReferenceColumn()) {
                return $association;
            }
        }

        return null;
    }
}
