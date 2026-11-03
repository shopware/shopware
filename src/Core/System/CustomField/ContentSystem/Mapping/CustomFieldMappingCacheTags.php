<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomField\ContentSystem\Mapping;

use Shopware\Core\Framework\Log\Package;

/**
 * Shared cache tag for custom-field metadata consulted by mapped content.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class CustomFieldMappingCacheTags
{
    public const METADATA = 'content-system-custom-field-mapping-metadata';
}
