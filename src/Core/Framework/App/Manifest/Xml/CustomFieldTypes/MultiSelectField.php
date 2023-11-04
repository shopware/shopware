<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\CustomFieldTypes;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
#[Package('core')]
class MultiSelectField extends SingleSelectField
{
    protected const COMPONENT_NAME = 'sw-multi-select';

    public static function fromXml(\DOMElement $element): CustomFieldType
    {
        return new self(self::parseSelect($element));
    }
}
