<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml;

use Shopware\Core\Framework\App\Manifest\Xml\CustomFieldTypes\CustomFieldType;
use Shopware\Core\Framework\App\Manifest\Xml\CustomFieldTypes\CustomFieldTypeFactory;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class RuleCondition extends XmlElement
{
    final public const TRANSLATABLE_FIELDS = ['name'];

    final public const REQUIRED_FIELDS = [
        'identifier',
        'name',
        'script',
    ];

    protected string $identifier;

    /**
     * @var array<string>
     */
    protected array $name = [];

    protected string $script;

    protected ?string $group = null;

    /**
     * @var CustomFieldType[]
     */
    protected array $constraints = [];

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
     * @return array<string>
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
     * @return CustomFieldType[]
     */
    public function getConstraints(): array
    {
        return $this->constraints;
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
        // translated
        if (\in_array($child->tagName, self::TRANSLATABLE_FIELDS, true)) {
            return self::mapTranslatedTag($child, $values);
        }

        if ($child->tagName === 'constraints') {
            $values[$child->tagName] = self::parseChildNodes(
                $child,
                static fn (\DOMElement $element): CustomFieldType => CustomFieldTypeFactory::createFromXml($element)
            );

            return $values;
        }

        $values[self::kebabCaseToCamelCase($child->tagName)] = $child->nodeValue;

        return $values;
    }
}
