<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\App\Manifest\XmlParserUtils;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\XmlReader;

/**
 * @internal only for use by the app-system
 *
 * @phpstan-type CustomFieldTypeArray array{name: string, config: array{label: array<string, string>, helpText: array<string, string>, customFieldPosition: int, validation?: 'required'}, allowCustomerWrite?: true, allowCartExpose?: true}
 */
#[Package('core')]
abstract class CustomFieldType extends XmlElement
{
    protected const TRANSLATABLE_FIELDS = [
        'label',
        'help-text',
    ];

    protected string $name;

    protected bool $required = false;

    protected bool $allowCustomerWrite = false;

    protected bool $allowCartExpose = false;

    protected int $position = 1;

    /**
     * @var array<string, string>
     */
    protected array $label = [];

    /**
     * @var array<string, string>
     */
    protected array $helpText = [];

    /**
     * @return CustomFieldTypeArray
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

        /** @phpstan-ignore-next-line because of the array method, PHPStan could not recognize the array shape correctly */
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
     * @return array{type: string, config: array<string, mixed>}
     */
    abstract protected function toEntityArray(): array;

    /**
     * @param list<string>|null $translatableFields
     *
     * @return array<string, mixed>
     */
    protected static function parse(\DOMElement $element, ?array $translatableFields = null): array
    {
        if (!$translatableFields) {
            $translatableFields = static::TRANSLATABLE_FIELDS;
        }

        $values = XmlParserUtils::parseAttributes($element);

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($child->tagName === 'options') {
                $values[$child->tagName] = static::parseOptions($child);

                continue;
            }

            // translated
            if (\in_array($child->tagName, $translatableFields, true)) {
                $values = XmlParserUtils::mapTranslatedTag($child, $values);

                continue;
            }

            $values[XmlParserUtils::kebabCaseToCamelCase($child->tagName)] = XmlReader::phpize($child->nodeValue);
        }

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function parseOptions(\DOMElement $child): array
    {
        $values = [];

        foreach ($child->childNodes as $option) {
            if (!$option instanceof \DOMElement) {
                continue;
            }

            $option = static::parse($option, ['name']);
            $key = (string) $option['value'];
            $values[$key] = $option['name'];
        }

        return $values;
    }
}
