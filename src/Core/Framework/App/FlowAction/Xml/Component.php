<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\FlowAction\Xml;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal (flag:FEATURE_NEXT_17540) - only for use by the app-system
 */
class Component extends XmlElement
{
    protected string $componentName;

    protected string $name;

    protected string $entity;

    protected array $label;

    public function __construct(array $data)
    {
        foreach ($data as $property => $value) {
            $this->$property = $value;
        }
    }

    public function getComponentName(): string
    {
        return $this->componentName;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEntity(): string
    {
        return $this->entity;
    }

    public function getLabel(): array
    {
        return $this->label;
    }

    public static function fromXml(\DOMElement $element): self
    {
        return new self(self::parse($element));
    }

    private static function parse(\DOMElement $element): array
    {
        $values = [];

        foreach ($element->attributes as $attribute) {
            $values['componentName'] = XmlUtils::phpize($attribute->value);
        }

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            // translated
            if (\in_array($child->tagName, ['label'], true)) {
                $values = self::mapTranslatedTag($child, $values);

                continue;
            }

            $values[$child->nodeName] = $child->nodeValue;
        }

        return $values;
    }
}
