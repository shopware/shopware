<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\CustomFieldTypes;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
abstract class CustomFieldType extends XmlElement
{
    protected const TRANSLATABLE_FIELDS = ['label', 'help-text'];

    /**
     * @var string
     */
    protected $name;

    /**
     * @var bool
     */
    protected $required = false;

    /**
     * @var int
     */
    protected $position = 1;

    /**
     * @var array<string, string>
     */
    protected $label;

    /**
     * @var array<string, string>
     */
    protected $helpText = [];

    abstract public static function fromXml(\DOMElement $element): self;

    public function toEntityPayload(): array
    {
        $entityArray = [
            'name' => $this->name,
            'config' => [
                'label' => $this->label,
                'helpText' => $this->helpText,
                'customFieldPosition' => $this->position,
            ],
        ];

        if ($this->required) {
            $entityArray['config']['validation'] = 'required';
        }

        return array_merge_recursive($entityArray, $this->toEntityArray());
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRequired(): bool
    {
        return $this->required;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getLabel(): array
    {
        return $this->label;
    }

    public function getHelpText(): array
    {
        return $this->helpText;
    }

    abstract protected function toEntityArray(): array;

    protected static function parse(\DOMElement $element, ?array $translatableFields = null): array
    {
        if (!$translatableFields) {
            $translatableFields = self::TRANSLATABLE_FIELDS;
        }

        $values = [];

        foreach ($element->attributes as $attribute) {
            $values[$attribute->name] = $attribute->value;
        }

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            // translated
            if (\in_array($child->tagName, $translatableFields, true)) {
                $values = self::mapTranslatedTag($child, $values);

                continue;
            }

            $values[self::kebabCaseToCamelCase($child->tagName)] = XmlUtils::phpize($child->nodeValue);
        }

        return $values;
    }
}
