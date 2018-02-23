<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Field;

class ChildrenAssociationField extends OneToManyAssociationField
{
    public function __construct(string $referenceClass)
    {
        parent::__construct('children', $referenceClass, 'parent_id', false, 'id');
    }
}
