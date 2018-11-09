<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentField;

class ParentFieldSerializer extends FkFieldSerializer
{
    public function getFieldClass(): string
    {
        return ParentField::class;
    }
}
