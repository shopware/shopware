<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;

class ChildrenAssociationFieldSerializer extends OneToManyAssociationFieldSerializer
{
    public function getFieldClass(): string
    {
        return ChildrenAssociationField::class;
    }
}
