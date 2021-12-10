<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Entity\Xml\Field;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Symfony\Component\Config\Util\XmlUtils;

class Field extends XmlElement
{
    protected string $name;

    protected bool $storeApiAware;

    public function __construct(array $data)
    {
        foreach ($data as $property => $value) {
            $this->$property = $value;
        }
    }

    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();
        unset($data['extensions']);

        return $data;
    }

    protected static function parse(\DOMElement $element): array
    {
        $values = [];

        if (is_iterable($element->attributes)) {
            foreach ($element->attributes as $attribute) {
                $name = self::kebabCaseToCamelCase($attribute->name);

                $values[$name] = XmlUtils::phpize($attribute->value);
            }
        }

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $values[self::kebabCaseToCamelCase($child->tagName)] = XmlUtils::phpize($child->nodeValue);
        }

        return $values;
    }
}
