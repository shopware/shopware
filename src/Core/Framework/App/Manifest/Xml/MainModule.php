<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml;

class MainModule extends XmlElement
{
    protected string $source;

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

    public static function parse(\DOMElement $element): array
    {
        $values = [];

        /** @var \DOMNamedNodeMap $attributes */
        $attributes = $element->attributes;

        foreach ($attributes as $attribute) {
            $values[$attribute->name] = $attribute->value;
        }

        return $values;
    }

    public function getSource(): string
    {
        return $this->source;
    }
}
