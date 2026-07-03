<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\Log\Package;

/**
 * Nested field for an element's universal style (JSON to ElementStyle). Composed into
 * ContentElementField; the registry-driven validation lives in ElementStyleFieldSerializer.
 *
 * @internal
 */
#[Package('framework')]
class ElementStyleField extends JsonField
{
    protected function getSerializerClass(): string
    {
        return ElementStyleFieldSerializer::class;
    }
}
