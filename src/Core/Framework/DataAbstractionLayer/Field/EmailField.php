<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\EmailFieldSerializer;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class EmailField extends StringField
{
    protected function getSerializerClass(): string
    {
        return EmailFieldSerializer::class;
    }
}
