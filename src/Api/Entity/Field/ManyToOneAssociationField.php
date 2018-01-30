<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Field;

class ManyToOneAssociationField extends ReferenceField implements AssociationInterface
{
    use AssociationTrait;

    /**
     * @var string
     */
    private $joinField;

    public function __construct(
        string $propertyName,
        string $storageName,
        string $referenceClass,
        bool $loadInBasic,
        string $referenceField = 'id',
        string $joinField = null
    ) {
        parent::__construct($storageName, $propertyName, $referenceField, $referenceClass);
        $this->loadInBasic = $loadInBasic;
        $this->referenceClass = $referenceClass;
        
        if (!$joinField) {
            $joinField = $storageName;
        }
        $this->joinField = $joinField;
    }

    public function getJoinField(): string
    {
        return $this->joinField;
    }
}
