<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomField\Xml\CustomFieldTypes;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * @internal
 */
#[Package('framework')]
class MediaSelectionField extends CustomFieldType
{
    protected function toEntityArray(): array
    {
        return [
            'type' => CustomFieldTypes::TEXT,
            'config' => [
                'componentName' => 'sw-media-field',
                'customFieldType' => 'media',
            ],
        ];
    }
}
