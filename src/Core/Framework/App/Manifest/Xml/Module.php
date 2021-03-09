<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml;

use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
class Module extends XmlElement
{
    public const TRANSLATABLE_FIELDS = ['label'];

    /**
     * @var array
     */
    protected $label;

    /**
     * @var string|null
     */
    protected $source = null;

    /**
     * @var string
     */
    protected $name;

    /**
     * @deprecated manifest:v1.1 will be required in future versions
     *
     * @var string|null
     */
    protected $parent = null;

    /**
     * @var int
     */
    protected $position = 1;

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

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getParent(): ?string
    {
        return $this->parent;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    private static function parse(\DOMElement $element): array
    {
        $values = [];

        foreach ($element->attributes as $attribute) {
            $values[self::kebabCaseToCamelCase($attribute->name)] = XmlUtils::phpize($attribute->value);
        }

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            // translated
            if (\in_array($child->tagName, self::TRANSLATABLE_FIELDS, true)) {
                $values = self::mapTranslatedTag($child, $values);

                continue;
            }
        }

        return $values;
    }
}
