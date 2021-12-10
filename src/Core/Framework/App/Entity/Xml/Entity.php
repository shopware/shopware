<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Entity\Xml;

use Shopware\Core\Framework\App\Entity\Xml\Field\Field;
use Shopware\Core\Framework\App\Entity\Xml\Field\FieldFactory;
use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
class Entity extends XmlElement
{
    protected string $name;

    protected bool $storeApiAware;

    public function __construct(array $data)
    {
        foreach ($data as $property => $value) {
            $this->$property = $value;
        }
    }

    public static function fromXml(\DOMElement $element): self
    {
        return new self(self::parse($element));
    }

    private static function parse(\DOMElement $element): array
    {
        $values = [];

        foreach ($element->attributes as $attribute) {
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

    private static function parseChild(\DOMElement $child, array $values): array
    {
        if ($child->tagName === 'fields') {
            $values[$child->tagName] = self::parseChildNodes($child, static function (\DOMElement $element): Field {
                return FieldFactory::createFromXml($element);
            });

            return $values;
        }

        $values[self::kebabCaseToCamelCase($child->tagName)] = $child->nodeValue;

        return $values;
    }

    public function isStoreApiAware(): bool
    {
        return $this->storeApiAware;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();
        unset($data['extensions']);
        return $data;
    }
}
