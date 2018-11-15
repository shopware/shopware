<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;

class ParentAssociationFieldSerializer extends ManyToOneAssociationFieldSerializer
{
    public function getFieldClass(): string
    {
        return ParentAssociationField::class;
    }
}
