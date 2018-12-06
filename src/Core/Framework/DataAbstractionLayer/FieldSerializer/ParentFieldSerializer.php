<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentFkField;

class ParentFieldSerializer extends FkFieldSerializer
{
    public function getFieldClass(): string
    {
        return ParentFkField::class;
    }
}
