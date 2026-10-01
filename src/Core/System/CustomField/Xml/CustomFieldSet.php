<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomField\Xml;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\App\Manifest\XmlParserUtils;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomField\Xml\CustomFieldTypes\CustomFieldType;
use Shopware\Core\System\CustomField\Xml\CustomFieldTypes\CustomFieldTypeFactory;

/**
 * @internal
 *
 * @phpstan-type CustomFieldSetArray array{id?: string, name?: string, global: bool, config: array<string, mixed>, relations: array<array<string, string>>, appId?: string, extensionName?: string, customFields: list<array<string, mixed>>}
 */
#[Package('framework')]
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
    public function toEntityArray(?string $appId, array &$existingRelations, array &$existingFields, ?string $existingSetId = null): array
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

                // custom_fields.name and custom_fields.type is immutable, thus only set if the set is new
                unset($fieldData['name']);
                unset($fieldData['type']);
                unset($existingFields[$field->getName()]);
            }

            return $fieldData;
        }, $this->fields);

        $set = [
            'global' => $this->global,
            'config' => [
                'label' => $this->label,
                'translated' => true,
            ],
            'relations' => $relations,
            'customFields' => $customFields,
        ];

        if ($appId !== null) {
            $set['appId'] = $appId;
        }

        if ($existingSetId) {
            $set['id'] = $existingSetId;
        } else {
            // custom_field_set.name is immutable, thus only set if the set is new
            $set['name'] = $this->name;
        }

        return $set;
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
        $values = XmlParserUtils::parseAttributes($element);

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
            return XmlParserUtils::mapTranslatedTag($child, $values);
        }

        if ($child->tagName === 'fields') {
            $values[$child->tagName] = XmlParserUtils::parseChildrenAsList(
                $child,
                static fn (\DOMElement $element): CustomFieldType => CustomFieldTypeFactory::createFromXml($element)
            );

            return $values;
        }

        if ($child->tagName === 'related-entities') {
            $values[XmlParserUtils::kebabCaseToCamelCase($child->tagName)] = XmlParserUtils::parseChildrenAsList(
                $child,
                static fn (\DOMElement $element): string => $element->tagName
            );

            return $values;
        }

        $values[XmlParserUtils::kebabCaseToCamelCase($child->tagName)] = $child->nodeValue;

        return $values;
    }
}
