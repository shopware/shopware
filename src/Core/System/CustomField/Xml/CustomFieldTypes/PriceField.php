<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomField\Xml\CustomFieldTypes;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * @internal
 */
#[Package('framework')]
class PriceField extends CustomFieldType
{
    protected function toEntityArray(): array
    {
        return [
            'type' => CustomFieldTypes::PRICE,
            'config' => [
                'type' => 'price',
                'componentName' => 'sw-price-field',
                'customFieldType' => 'price',
            ],
        ];
    }
}
