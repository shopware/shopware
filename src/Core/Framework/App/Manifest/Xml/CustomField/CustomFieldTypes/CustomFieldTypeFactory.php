<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes;

use Shopware\Core\Framework\App\Exception\CustomFieldTypeNotFoundException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class CustomFieldTypeFactory
{
    private const TAG_TO_CLASS_MAPPING = [
        'int' => IntField::class,
        'float' => FloatField::class,
        'text' => TextField::class,
        'text-area' => TextAreaField::class,
        'bool' => BoolField::class,
        'datetime' => DateTimeField::class,
        'single-select' => SingleSelectField::class,
        'multi-select' => MultiSelectField::class,
        'single-entity-select' => SingleEntitySelectField::class,
        'multi-entity-select' => MultiEntitySelectField::class,
        'color-picker' => ColorPickerField::class,
        'media-selection' => MediaSelectionField::class,
        'price' => PriceField::class,
    ];

    public static function createFromXml(\DOMElement $element): CustomFieldType
    {
        /** @var class-string<CustomFieldType>|null $fieldClass */
        $fieldClass = self::TAG_TO_CLASS_MAPPING[$element->tagName] ?? null;

        if (!$fieldClass) {
            throw new CustomFieldTypeNotFoundException($element->tagName);
        }

        return $fieldClass::fromXml($element);
    }
}
