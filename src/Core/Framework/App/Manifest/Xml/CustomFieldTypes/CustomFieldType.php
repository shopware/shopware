<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\CustomFieldTypes;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\XmlReader;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
#[Package('core')]
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
     * @var bool
     */
    protected $allowCustomerWrite = false;

    protected bool $allowCartExpose = false;

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

    /**
     * @return array<string, mixed>
     */
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

        if ($this->allowCustomerWrite) {
            $entityArray['allowCustomerWrite'] = true;
        }

        if ($this->allowCartExpose) {
            $entityArray['allowCartExpose'] = true;
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

    /**
     * @return array<string, string>
     */
    public function getLabel(): array
    {
        return $this->label;
    }

    /**
     * @return array<string, string>
     */
    public function getHelpText(): array
    {
        return $this->helpText;
    }

    public function isAllowCustomerWrite(): bool
    {
        return $this->allowCustomerWrite;
    }

    public function isAllowCartExpose(): bool
    {
        return $this->allowCartExpose;
    }

    /**
     * @return array<string, mixed>
     */
    abstract protected function toEntityArray(): array;

    /**
     * @param string[]|null $translatableFields
     *
     * @return mixed[]
     */
    protected static function parse(\DOMElement $element, ?array $translatableFields = null): array
    {
        if (!$translatableFields) {
            $translatableFields = self::TRANSLATABLE_FIELDS;
        }

        $values = [];

        foreach ($element->attributes ?? [] as $attribute) {
            \assert($attribute instanceof \DOMAttr);
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

            $values[self::kebabCaseToCamelCase($child->tagName)] = XmlReader::phpize($child->nodeValue);
        }

        return $values;
    }
}
