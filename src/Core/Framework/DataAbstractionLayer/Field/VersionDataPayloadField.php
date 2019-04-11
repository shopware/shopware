<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\VersionDataPayloadFieldSerializer;

/**
 * @internal
 */
class VersionDataPayloadField extends JsonField
{
    protected function getSerializerClass(): string
    {
        return VersionDataPayloadFieldSerializer::class;
    }
}
