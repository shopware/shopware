<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class Storefront extends XmlElement
{
    protected int $templateLoadPriority = 0;

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

            if ($node->tagName === 'template-load-priority') {
                $values['templateLoadPriority'] = (int) $node->textContent;
            }
        }

        return $values;
    }

    public function getTemplateLoadPriority(): int
    {
        return $this->templateLoadPriority;
    }
}
