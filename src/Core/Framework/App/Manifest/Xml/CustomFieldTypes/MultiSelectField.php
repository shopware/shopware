<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\CustomFieldTypes;

class MultiSelectField extends SingleSelectField
{
    protected const COMPONENT_NAME = 'sw-multi-select';

    public static function fromXml(\DOMElement $element): CustomFieldType
    {
        return new self(self::parseSelect($element));
    }
}
