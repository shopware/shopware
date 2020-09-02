<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml;

use Symfony\Component\Config\Util\XmlUtils;

class Module extends XmlElement
{
    /**
     * @var array
     */
    protected $label;

    /**
     * @var string
     */
    protected $source;

    /**
     * @var string
     */
    protected $name;

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

    public function getLabel(): array
    {
        return $this->label;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getName(): string
    {
        return $this->name;
    }

    private static function parse(\DOMElement $element): array
    {
        $values = [];

        foreach ($element->attributes as $attribute) {
            $values[$attribute->name] = XmlUtils::phpize($attribute->value);
        }

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($child->tagName === 'label') {
                $values = self::mapTranslatedTag($child, $values);

                continue;
            }
        }

        return $values;
    }
}
