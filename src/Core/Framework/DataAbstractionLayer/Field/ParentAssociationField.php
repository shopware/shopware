<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

class ParentAssociationField extends ManyToOneAssociationField
{
    public function __construct(string $referenceClass, string $referenceField = 'id')
    {
        parent::__construct('parent', 'parent_id', $referenceClass, $referenceField, false);
    }
}
