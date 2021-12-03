<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\FlowAction\Xml;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;

/**
 * @internal (flag:FEATURE_NEXT_17540) - only for use by the app-system
 */
class Metadata extends XmlElement
{
    public const TRANSLATABLE_FIELDS = [
        'label',
        'description',
    ];

    public const REQUIRED_FIELDS = [
        'label',
        'name',
        'url',
    ];

    protected array $label;

    protected array $description;

    protected string $name;

    protected string $url;

    protected array $requirements = [];

    protected ?string $icon = null;

    protected ?string $swIcon = null;

    private function __construct(array $data)
    {
        $this->validateRequiredElements($data, self::REQUIRED_FIELDS);

        foreach ($data as $property => $value) {
            $this->$property = $value;
        }
    }

    public function getLabel(): array
    {
        return $this->label;
    }

    public function getDescription(): array
    {
        return $this->description;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getRequirements(): array
    {
        return $this->requirements;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getSwIcon(): ?string
    {
        return $this->swIcon;
    }

    public static function fromXml(\DOMElement $element): self
    {
        return new self(self::parse($element));
    }

    public function toArray(string $defaultLocale): array
    {
        $data = parent::toArray($defaultLocale);

        foreach (self::TRANSLATABLE_FIELDS as $TRANSLATABLE_FIELD) {
            $translatableField = self::kebabCaseToCamelCase($TRANSLATABLE_FIELD);

            $data[$translatableField] = $this->ensureTranslationForDefaultLanguageExist(
                $data[$translatableField],
                $defaultLocale
            );
        }

        return $data;
    }

    private static function parse(\DOMElement $element): array
    {
        $values = [];

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            // translated
            if (\in_array($child->tagName, self::TRANSLATABLE_FIELDS, true)) {
                $values = self::mapTranslatedTag($child, $values);

                continue;
            }

            if ($child->nodeName === 'requirements') {
                $values[$child->nodeName][] = $child->nodeValue;

                continue;
            }

            if ($child->nodeName === 'sw-icon') {
                $values['swIcon'] = $child->nodeValue;

                continue;
            }

            $values[$child->nodeName] = $child->nodeValue;
        }

        return $values;
    }
}
