<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Config;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal
 */
abstract class CustomEntityFlag extends XmlElement
{
    protected string $technicalName;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        $this->assign($data);
    }

    public function getName(): string
    {
        return $this->technicalName;
    }

    abstract public static function fromXml(\DOMElement $element): CustomEntityFlag;

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();
        unset($data['extensions']);
        unset($data['technicalName']);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function parse(\DOMElement $element): array
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

            $values = $this->parseChild($child, $values);
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    protected function parseChild(\DomElement $child, array $values): array
    {
        /** @var string $nodeValue */
        $nodeValue = $child->nodeValue;
        $values[self::kebabCaseToCamelCase($child->tagName)] = XmlUtils::phpize($nodeValue);

        return $values;
    }
}
