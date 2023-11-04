<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\CustomFieldTypes;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\XmlReader;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
#[Package('core')]
class SingleSelectField extends CustomFieldType
{
    protected const TRANSLATABLE_FIELDS = ['label', 'help-text', 'placeholder'];

    protected const COMPONENT_NAME = 'sw-single-select';

    /**
     * @var array
     */
    protected $placeholder = [];

    /**
     * @var array
     */
    protected $options;

    protected function __construct(array $data)
    {
        foreach ($data as $property => $value) {
            $this->$property = $value;
        }
    }

    public static function fromXml(\DOMElement $element): CustomFieldType
    {
        return new self(self::parseSelect($element));
    }

    public function getPlaceholder(): array
    {
        return $this->placeholder;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    protected function toEntityArray(): array
    {
        $options = [];

        foreach ($this->options as $key => $names) {
            $options[] = [
                'label' => $names,
                'value' => $key,
            ];
        }

        return [
            'type' => CustomFieldTypes::SELECT,
            'config' => [
                'placeholder' => $this->placeholder,
                // use $this so child classes can override the const
                'componentName' => $this::COMPONENT_NAME,
                'customFieldType' => 'select',
                'options' => $options,
            ],
        ];
    }

    protected static function parseSelect(\DOMElement $element): array
    {
        $values = [];

        foreach ($element->attributes as $attribute) {
            \assert($attribute instanceof \DOMAttr);
            $values[$attribute->name] = $attribute->value;
        }

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($child->tagName === 'options') {
                $values[$child->tagName] = self::parseOptions($child);

                continue;
            }

            if (\in_array($child->tagName, self::TRANSLATABLE_FIELDS, true)) {
                $values = self::mapTranslatedTag($child, $values);
            } else {
                $values[self::kebabCaseToCamelCase($child->tagName)] = XmlReader::phpize($child->nodeValue);
            }
        }

        return $values;
    }

    protected static function parseOptions(\DOMElement $child): array
    {
        $values = [];

        foreach ($child->childNodes as $option) {
            if (!$option instanceof \DOMElement) {
                continue;
            }

            $option = self::parse($option, ['name']);
            /** @var string $key */
            $key = $option['value'];
            /** @var array<string, string> $names */
            $names = $option['name'];

            $values[$key] = $names;
        }

        return $values;
    }
}
