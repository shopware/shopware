<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\CustomFieldTypes;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
#[Package('core')]
class MultiEntitySelectField extends SingleEntitySelectField
{
    protected const COMPONENT_NAME = 'sw-entity-multi-id-select';

    public static function fromXml(\DOMElement $element): CustomFieldType
    {
        return new self(self::parse($element, self::TRANSLATABLE_FIELDS));
    }
}
