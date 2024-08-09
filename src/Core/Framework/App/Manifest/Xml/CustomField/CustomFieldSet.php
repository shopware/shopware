<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\CustomField;

use Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes\CustomFieldType;
use Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes\CustomFieldTypeFactory;
use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\XmlReader;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class CustomFieldSet extends XmlElement
{
    protected const REQUIRED_FIELDS = [
        'label',
        'name',
        'relatedEntities',
        'fields',
    ];
    private const TRANSLATABLE_FIELDS = ['label'];

    /**
     * @var array<string, string>
     */
    protected array $label;

    protected string $name;

    /**
     * @var list<string>
     */
    protected array $relatedEntities = [];

    /**
     * @var list<CustomFieldType>
     */
    protected array $fields = [];

    protected bool $global = false;

    /**
     * @param array<string, string> $existingRelations passed by reference, as still configured relations are removed, thus this array consist only obsolete relations after the call
     * @param array<string, string> $existingFields passed by reference, as still configured fields are removed, thus this array consist only obsolete fields after the call
     *
     * @return CustomFieldSetArray
     */
    public function toEntityArray(string $appId, array &$existingRelations, array &$existingFields): array
    {
        $relations = array_map(static function (string $entity) use (&$existingRelations): array {
            $relationData = ['entityName' => $entity];
            if (\array_key_exists($entity, $existingRelations)) {
                $relationData['id'] = $existingRelations[$entity];

                unset($existingRelations[$entity]);
            }

            return $relationData;
        }, $this->relatedEntities);

        $customFields = array_map(static function (CustomFieldType $field) use (&$existingFields): array {
            $fieldData = $field->toEntityPayload();
            if (\array_key_exists($field->getName(), $existingFields)) {
                $fieldData['id'] = $existingFields[$field->getName()];

                unset($existingFields[$field->getName()]);
            }

            return $fieldData;
        }, $this->fields);

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

    /**
     * @return array<string, string>
     */
    public function getLabel(): array
    {
        return $this->label;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return list<string>
     */
    public function getRelatedEntities(): array
    {
        return $this->relatedEntities;
    }

    /**
     * @return list<CustomFieldType>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    public function getGlobal(): bool
    {
        return $this->global;
    }

    protected static function parse(\DOMElement $element): array
    {
        $values = [];

        foreach ($element->attributes as $attribute) {
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }
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

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
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
