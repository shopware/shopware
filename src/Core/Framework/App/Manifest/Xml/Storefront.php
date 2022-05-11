<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml;

class Storefront extends XmlElement
{
    protected int $loadPriority = 0;

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

        foreach ($element->childNodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            if ($node->tagName === 'load-priority') {
                $values['loadPriority'] = (int) $node->textContent;
            }
        }

        return $values;
    }

    public function getLoadPriority(): int
    {
        return $this->loadPriority;
    }
}
