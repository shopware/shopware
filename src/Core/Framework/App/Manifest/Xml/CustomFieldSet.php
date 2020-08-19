<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml;

use Shopware\Core\Framework\App\Manifest\Xml\CustomFieldTypes\CustomFieldType;
use Shopware\Core\Framework\App\Manifest\Xml\CustomFieldTypes\CustomFieldTypeFactory;

class CustomFieldSet extends XmlElement
{
    /**
     * @var array
     */
    protected $label;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string[]
     */
    protected $relatedEntities;

    /**
     * @var CustomFieldType[]
     */
    protected $fields = [];

    private function __construct(array $data)
    {
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
        $relations = array_map(static function (string $entity) {
            return ['entityName' => $entity];
        }, $this->relatedEntities);

        $customFields = array_map(static function (CustomFieldType $field) {
            return $field->toEntityPayload();
        }, $this->fields);

        return [
            'name' => $this->name,
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
     * @return string[]
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

    private static function parse(\DOMElement $element): array
    {
        $values = [];
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
        if ($child->tagName === 'label') {
            return self::mapTranslatedTag($child, $values);
        }

        if ($child->tagName === 'fields') {
            $values[$child->tagName] = self::parseChildNodes(
                $child,
                static function (\DOMElement $element): CustomFieldType {
                    return CustomFieldTypeFactory::createFromXml($element);
                }
            );

            return $values;
        }

        if ($child->tagName === 'related-entities') {
            $values[self::snakeCaseToCamelCase($child->tagName)] = self::parseChildNodes(
                $child,
                static function (\DOMElement $element): string {
                    return $element->tagName;
                }
            );

            return $values;
        }

        $values[self::snakeCaseToCamelCase($child->tagName)] = $child->nodeValue;

        return $values;
    }
}
