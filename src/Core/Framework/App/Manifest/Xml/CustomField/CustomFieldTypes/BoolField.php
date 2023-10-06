<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class BoolField extends CustomFieldType
{
    private function __construct(array $data)
    {
        foreach ($data as $property => $value) {
            $this->$property = $value;
        }
    }

    public static function fromXml(\DOMElement $element): CustomFieldType
    {
        return new self(self::parse($element));
    }

    protected function toEntityArray(): array
    {
        return [
            'type' => CustomFieldTypes::BOOL,
            'config' => [
                'type' => 'checkbox',
                'componentName' => 'sw-field',
                'customFieldType' => 'checkbox',
            ],
        ];
    }
}
