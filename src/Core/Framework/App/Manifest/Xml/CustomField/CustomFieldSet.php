<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\CustomField;

use Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes\CustomFieldType;
use Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes\CustomFieldTypeFactory;
use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\App\Manifest\XmlParserUtils;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @phpstan-type CustomFieldSetArray array{name: string, global: bool, config: array<string, mixed>, relations: array<array<string, string>>, appId: string, customFields: list<array<string, mixed>>}
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
     * @return CustomFieldSetArray
     */
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
