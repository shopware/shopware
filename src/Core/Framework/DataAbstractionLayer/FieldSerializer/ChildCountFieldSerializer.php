<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildCountField;

class ChildCountFieldSerializer extends IntFieldSerializer
{
    public function getFieldClass(): string
    {
        return ChildCountField::class;
    }
}
