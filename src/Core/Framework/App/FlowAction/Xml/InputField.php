<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\FlowAction\Xml;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class InputField extends XmlElement
{
    private const TRANSLATABLE_FIELDS = [
        'label',
        'place-holder',
        'helpText',
    ];

    private const BOOLEAN_FIELD = ['required'];

    protected ?string $name = null;

    protected ?array $label = null;

    protected ?array $placeHolder = null;

    protected ?bool $required = null;

    protected ?array $helpText = [];

    protected ?string $defaultValue = null;

    protected ?array $options = [];

    protected ?string $type = null;

    public function __construct(array $data)
    {
        foreach ($data as $property => $value) {
            $this->$property = $value;
        }
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getLabel(): ?array
    {
        return $this->label;
    }

    public function getPlaceHolder(): ?array
    {
        return $this->placeHolder;
    }

    public function getRequired(): ?bool
    {
        return $this->required;
    }

    public function getHelpText(): ?array
    {
        return $this->helpText;
    }

    public function getDefaultValue(): ?string
    {
        return $this->defaultValue;
    }

    public function getOptions(): ?array
    {
        return $this->options;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public static function fromXml(\DOMElement $element): self
    {
        return new self(self::parse($element));
    }

    private static function parse(\DOMElement $element): array
    {
        $values = [];

        $values['type'] = $element->getAttribute('type') ?: 'text';

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            // translated
            if (\in_array($child->tagName, self::TRANSLATABLE_FIELDS, true)) {
                $values = self::mapTranslatedTag($child, $values);

                continue;
            }

            if (\in_array($child->nodeName, self::BOOLEAN_FIELD, true)) {
                $values[$child->nodeName] = $child->nodeValue === 'true';

                continue;
            }

            if ($child->nodeName === 'options') {
                $values[$child->nodeName] = self::parseOptions($child);

                continue;
            }

            $values[$child->nodeName] = $child->nodeValue;
        }

        return $values;
    }

    private static function parseOptions(\DOMElement $element): array
    {
        $values = [];

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $values[] = self::parseOption($child);
        }

        return $values;
    }

    private static function parseOption(\DOMElement $element): array
    {
        $values = [];

        $values['value'] = $element->getAttribute('value');

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $values = self::mapTranslatedTag($child, $values);
        }

        return $values;
    }
}
