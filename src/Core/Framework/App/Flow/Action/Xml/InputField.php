<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Flow\Action\Xml;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\App\Manifest\XmlParserUtils;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class InputField extends XmlElement
{
    private const TRANSLATABLE_FIELDS = [
        'label',
        'place-holder',
        'helpText',
    ];

    private const BOOLEAN_FIELD = ['required'];

    protected ?string $name = null;

    /**
     * @var array<string, string>|null
     */
    protected ?array $label = null;

    /**
     * @var array<string, string>|null
     */
    protected ?array $placeHolder = null;

    protected ?bool $required = null;

    /**
     * @var array<string, string>|null
     */
    protected ?array $helpText = null;

    protected ?string $defaultValue = null;

    /**
     * @var list<array{value: string, label: array<string, string>}>|null
     */
    protected ?array $options = [];

    protected ?string $type = null;

    protected string $id;

    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @return array<string, string>|null
     */
    public function getLabel(): ?array
    {
        return $this->label;
    }

    /**
     * @return array<string, string>|null
     */
    public function getPlaceHolder(): ?array
    {
        return $this->placeHolder;
    }

    public function getRequired(): ?bool
    {
        return $this->required;
    }

    /**
     * @return array<string, string>|null
     */
    public function getHelpText(): ?array
    {
        return $this->helpText;
    }

    public function getDefaultValue(): ?string
    {
        return $this->defaultValue;
    }

    /**
     * @return list<array{value: string, label: array<string, string>}>|null
     */
    public function getOptions(): ?array
    {
        return $this->options;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function toArray(string $defaultLocale): array
    {
        $data = parent::toArray($defaultLocale);

        return array_merge($data, [
            'name' => $this->getName(),
            'label' => $this->ensureTranslationForDefaultLanguageExist($this->getLabel(), $defaultLocale),
            'placeHolder' => $this->ensureTranslationForDefaultLanguageExist($this->getPlaceHolder(), $defaultLocale),
            'required' => $this->getRequired(),
            'helpText' => $this->ensureTranslationForDefaultLanguageExist($this->getHelpText(), $defaultLocale),
            'defaultValue' => $this->getDefaultValue(),
            'options' => array_map(
                fn (array $option): array => [
                    ...$option,
                    'label' => $this->ensureTranslationForDefaultLanguageExist($option['label'], $defaultLocale),
                ],
                $this->getOptions() ?? []
            ),
            'type' => $this->getType(),
        ]);
    }

    protected static function parse(\DOMElement $element): array
    {
        $values = [];

        $values['type'] = $element->getAttribute('type') ?: 'text';

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            // translated
            if (\in_array($child->tagName, self::TRANSLATABLE_FIELDS, true)) {
                $values = XmlParserUtils::mapTranslatedTag($child, $values);

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

    /**
     * @return list<array{value: string, label: array<string, string>}>
     */
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

    /**
     * @return array{value: string, label: array<string, string>}
     */
    private static function parseOption(\DOMElement $element): array
    {
        $values = ['label' => []];

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $values = XmlParserUtils::mapTranslatedTag($child, $values);
        }

        return ['value' => $element->getAttribute('value'), 'label' => $values['label']];
    }
}
