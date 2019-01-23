<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Field\QueriesAssociationField;

class QueriesAssociationFieldSerializer extends ChildrenAssociationFieldSerializer
{
    public function getFieldClass(): string
    {
        return QueriesAssociationField::class;
    }
}
