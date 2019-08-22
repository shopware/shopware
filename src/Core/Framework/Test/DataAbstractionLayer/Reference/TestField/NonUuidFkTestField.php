<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Reference\TestField;

use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Reference\TestFieldSerializer\NonUuidFkTestFieldSerializer;

class NonUuidFkTestField extends FkField
{
    protected function getSerializerClass(): string
    {
        return NonUuidFkTestFieldSerializer::class;
    }
}
