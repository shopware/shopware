<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\Log\Package;

/**
 * Field for storing context providers map (JSON to array<string, ContextProvider>).
 *
 * @internal
 */
#[Package('discovery')]
class ContextProvidersField extends JsonField
{
    protected function getSerializerClass(): string
    {
        return ContextProvidersFieldSerializer::class;
    }
}
