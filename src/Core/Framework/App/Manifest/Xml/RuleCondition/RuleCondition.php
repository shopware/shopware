<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\RuleCondition;

use Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes\CustomFieldType;
use Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes\CustomFieldTypeFactory;
use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\App\Manifest\XmlParserUtils;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class RuleCondition extends XmlElement
{
    protected const REQUIRED_FIELDS = [
        'identifier',
        'name',
        'script',
    ];

    private const TRANSLATABLE_FIELDS = [
        'name',
    ];

    protected string $identifier;

    /**
     * @var array<string, string>
     */
    protected array $name = [];

    protected string $script;

    protected ?string $group = null;

    /**
     * @var list<CustomFieldType>
     */
    protected array $constraints = [];

    public function toArray(string $defaultLocale): array
    {
        $data = parent::toArray($defaultLocale);

        foreach (self::TRANSLATABLE_FIELDS as $TRANSLATABLE_FIELD) {
            $translatableField = self::kebabCaseToCamelCase($TRANSLATABLE_FIELD);

            $data[$translatableField] = $this->ensureTranslationForDefaultLanguageExist(
                $data[$translatableField],
                $defaultLocale
            );
        }

        $data['config'] = array_map(static fn (CustomFieldType $field) => $field->toEntityPayload(), $this->constraints);

        return $data;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * @return array<string, string>
     */
    public function getName(): array
    {
        return $this->name;
    }

    public function getScript(): string
    {
        return $this->script;
    }

    public function getGroup(): ?string
    {
        return $this->group;
    }

    /**
     * @return list<CustomFieldType>
     */
    public function getConstraints(): array
    {
        return $this->constraints;
    }

    protected static function parse(\DOMElement $element): array
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

        if ($child->tagName === 'constraints') {
            $values[$child->tagName] = XmlParserUtils::parseChildrenAsList(
                $child,
                static fn (\DOMElement $element): CustomFieldType => CustomFieldTypeFactory::createFromXml($element)
            );

            return $values;
        }

        $values[XmlParserUtils::kebabCaseToCamelCase($child->tagName)] = $child->nodeValue;

        return $values;
    }
}
