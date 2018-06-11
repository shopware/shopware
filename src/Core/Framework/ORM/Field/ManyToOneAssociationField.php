<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Field;

class ManyToOneAssociationField extends ReferenceField implements AssociationInterface
{
    use AssociationTrait;

    public function __construct(
        string $propertyName,
        string $storageName,
        string $referenceClass,
        bool $loadInBasic,
        string $referenceField = 'id'
    ) {
        parent::__construct($storageName, $propertyName, $referenceField, $referenceClass);
        $this->loadInBasic = $loadInBasic;
        $this->referenceClass = $referenceClass;
    }
}
