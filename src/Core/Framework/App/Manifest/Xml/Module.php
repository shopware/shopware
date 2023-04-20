<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\XmlReader;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
#[Package('core')]
class Module extends XmlElement
{
    final public const TRANSLATABLE_FIELDS = ['label'];

    /**
     * @var array<string, string>
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
     * @var string
     */
    protected $parent;

    /**
     * @var int
     */
    protected $position = 1;

    /**
     * @param array<string, mixed> $data
     */
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

    /**
     * @return array<string, string>
     */
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

    /**
     * @return array<mixed>
     */
    private static function parse(\DOMElement $element): array
    {
        $values = [];

        foreach ($element->attributes ?? [] as $attribute) {
            \assert($attribute instanceof \DOMAttr);
            $values[self::kebabCaseToCamelCase($attribute->name)] = XmlReader::phpize($attribute->value);
        }

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            // translated
            if (\in_array($child->tagName, self::TRANSLATABLE_FIELDS, true)) {
                $values = self::mapTranslatedTag($child, $values);
            }
        }

        return $values;
    }
}
