<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\App\Manifest\XmlParserUtils;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomEntity\Xml\Config\ConfigXmlElement;
use Shopware\Core\System\CustomEntity\Xml\Field\Field;
use Shopware\Core\System\CustomEntity\Xml\Field\FieldFactory;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
#[Package('core')]
class Entity extends XmlElement
{
    protected string $name;

    protected ?bool $cmsAware = null;

    protected bool $customFieldsAware = false;

    protected ?string $labelProperty = null;

    /**
     * @var list<Field>
     */
    protected array $fields = [];

    /**
     * @var array<string, ConfigXmlElement>
     */
    protected array $flags = [];

    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();
        unset($data['extensions']);

        return $data;
    }

    /**
     * @return list<Field>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    public function hasField(string $fieldName): bool
    {
        return $this->getField($fieldName) !== null;
    }

    public function getField(string $fieldName): ?Field
    {
        foreach ($this->getFields() as $field) {
            if ($field->getName() === $fieldName) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @param list<Field> $fields
     */
    public function setFields(array $fields): void
    {
        $this->fields = $fields;
    }

    /**
     * @return array<string, ConfigXmlElement> $flags
     */
    public function getFlags(): array
    {
        return $this->flags;
    }

    /**
     * @param array<string, ConfigXmlElement> $flags
     */
    public function setFlags(array $flags): void
    {
        $this->flags = $flags;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isCmsAware(): ?bool
    {
        return $this->cmsAware;
    }

    public function isCustomFieldsAware(): bool
    {
        return $this->customFieldsAware;
    }

    public function getLabelProperty(): ?string
    {
        return $this->labelProperty;
    }

    protected static function parse(\DOMElement $element): array
    {
        $values = [];

        foreach ($element->attributes as $attribute) {
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }
            $name = self::kebabCaseToCamelCase($attribute->name);

            $values[$name] = XmlUtils::phpize($attribute->value);
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
        if ($child->tagName === 'fields') {
            $values[$child->tagName] = XmlParserUtils::parseChildrenAsList($child, static fn (\DOMElement $element): Field => FieldFactory::createFromXml($element));

            return $values;
        }

        $values[XmlParserUtils::kebabCaseToCamelCase($child->tagName)] = $child->nodeValue;

        return $values;
    }
}
