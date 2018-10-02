<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Field;

use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\ORM\Write\EntityExistence;
use Shopware\Core\Framework\ORM\Write\FieldException\MalformatDataException;

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

    protected function invoke(EntityExistence $existence, KeyValuePair $data): \Generator
    {
        $key = $data->getKey();
        $value = $data->getValue();

        if (!\is_array($value)) {
            throw new MalformatDataException($this->path . '/' . $key, 'Value must be an array.');
        }

        $isNumeric = array_keys($value) === range(0, \count($value) - 1);

        $mappingAssociation = $this->getMappingAssociation();

        foreach ($value as $keyValue => $subresources) {
            $mapped = $subresources;
            if ($mappingAssociation) {
                $mapped = $this->map($mappingAssociation, $subresources);
            }

            if (!\is_array($mapped)) {
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

    private function getMappingAssociation(): ?ManyToOneAssociationField
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

    private function map(ManyToOneAssociationField $association, $data): array
    {
        // not only foreign key provided? data is provided as insert or update command
        if (\count($data) > 1) {
            return [$association->getPropertyName() => $data];
        }

        // no id provided? data is provided as insert command (like create category in same request with the product)
        if (!isset($data[$association->getReferenceField()])) {
            return [$association->getPropertyName() => $data];
        }

        //only foreign key provided? entity should only be linked
        /*e.g
            [
                categories => [
                    ['id' => {id}],
                    ['id' => {id}]
                ]
            ]
        */
        /** @var ManyToOneAssociationField $association */
        $fk = $this->referenceClass::getFields()->getByStorageName(
            $association->getStorageName()
        );

        if (!$fk) {
            trigger_error(sprintf('Foreign key for association %s not found', $association->getPropertyName()));

            return [$association->getPropertyName() => $data];
        }

        /* @var FkField $fk */
        return [$fk->getPropertyName() => $data[$association->getReferenceField()]];
    }
}
