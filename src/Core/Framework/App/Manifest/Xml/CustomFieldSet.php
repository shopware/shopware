<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml;

use Shopware\Core\Framework\App\Manifest\Xml\CustomFieldTypes\CustomFieldType;
use Shopware\Core\Framework\App\Manifest\Xml\CustomFieldTypes\CustomFieldTypeFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\XmlReader;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
#[Package('core')]
class CustomFieldSet extends XmlElement
{
    final public const TRANSLATABLE_FIELDS = ['label'];

    final public const REQUIRED_FIELDS = [
        'label',
        'name',
        'relatedEntities',
        'fields',
    ];

    protected array $label;

    protected string $name;

    /**
     * @var array<string>
     */
    protected array $relatedEntities = [];

    /**
     * @var CustomFieldType[]
     */
    protected array $fields = [];

    protected bool $global = false;

    private function __construct(array $data)
    {
        $this->validateRequiredElements($data, self::REQUIRED_FIELDS);

        foreach ($data as $property => $value) {
            $this->$property = $value;
        }
    }

    public static function fromXml(\DOMElement $element): self
    {
        return new self(self::parse($element));
    }

    public function toEntityArray(string $appId): array
    {
        $relations = array_map(static fn (string $entity) => ['entityName' => $entity], $this->relatedEntities);

        $customFields = array_map(static fn (CustomFieldType $field) => $field->toEntityPayload(), $this->fields);

        return [
            'name' => $this->name,
            'global' => $this->global,
            'config' => [
                'label' => $this->label,
                'translated' => true,
            ],
            'relations' => $relations,
            'appId' => $appId,
            'customFields' => $customFields,
        ];
    }

    public function getLabel(): array
    {
        return $this->label;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<string>
     */
    public function getRelatedEntities(): array
    {
        return $this->relatedEntities;
    }

    /**
     * @return CustomFieldType[]
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    public function getGlobal(): bool
    {
        return $this->global;
    }

    private static function parse(\DOMElement $element): array
    {
        $values = [];

        foreach ($element->attributes ?? [] as $attribute) {
            \assert($attribute instanceof \DOMAttr);
            $values[$attribute->name] = XmlReader::phpize($attribute->value);
        }

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $values = self::parseChild($child, $values);
        }

        return $values;
    }

    private static function parseChild(\DOMElement $child, array $values): array
    {
        // translated
        if (\in_array($child->tagName, self::TRANSLATABLE_FIELDS, true)) {
            return self::mapTranslatedTag($child, $values);
        }

        if ($child->tagName === 'fields') {
            $values[$child->tagName] = self::parseChildNodes(
                $child,
                static fn (\DOMElement $element): CustomFieldType => CustomFieldTypeFactory::createFromXml($element)
            );

            return $values;
        }

        if ($child->tagName === 'related-entities') {
            $values[self::kebabCaseToCamelCase($child->tagName)] = self::parseChildNodes(
                $child,
                static fn (\DOMElement $element): string => $element->tagName
            );

            return $values;
        }

        $values[self::kebabCaseToCamelCase($child->tagName)] = $child->nodeValue;

        return $values;
    }
}
