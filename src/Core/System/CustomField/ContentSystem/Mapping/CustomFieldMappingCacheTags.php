<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomField\ContentSystem\Mapping;

use Shopware\Core\Framework\Log\Package;

/**
 * Cache tags for custom-field metadata consulted by mapped content.
 *
 * @internal
 */
#[Package('framework')]
final class CustomFieldMappingCacheTags
{
    public static function candidate(string $customFieldId): string
    {
        return 'content-system-custom-field-' . $customFieldId;
    }

    public static function set(string $customFieldSetId): string
    {
        return 'content-system-custom-field-set-' . $customFieldSetId;
    }
}
