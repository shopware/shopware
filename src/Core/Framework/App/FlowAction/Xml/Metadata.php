<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\FlowAction\Xml;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class Metadata extends XmlElement
{
    final public const TRANSLATABLE_FIELDS = [
        'label',
        'description',
        'headline',
    ];

    final public const REQUIRED_FIELDS = [
        'label',
        'name',
        'url',
    ];

    private const BOOLEAN_FIELD = ['delayable'];

    /**
     * @var array<string, mixed>
     */
    protected array $label;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $description = null;

    protected string $name;

    protected string $url;

    /**
     * @var array<string, mixed>
     */
    protected array $requirements = [];

    protected ?string $icon = null;

    protected ?string $swIcon = null;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $headline = null;

    protected bool $delayable = false;

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(array $data)
    {
        $this->validateRequiredElements($data, self::REQUIRED_FIELDS);

        foreach ($data as $property => $value) {
            $this->$property = $value;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getLabel(): array
    {
        return $this->label;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getDescription(): ?array
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

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @return array<string, mixed>|null
     */
    public function getHeadline(): ?array
    {
        return $this->headline;
    }

    public function getDelayable(): bool
    {
        return $this->delayable;
    }

    public function setDelayable(bool $delayable = false): void
    {
        $this->delayable = $delayable;
    }

    public static function fromXml(\DOMElement $element): self
    {
        return new self(self::parse($element));
    }

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @return array<mixed>
     */
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

            if (\in_array($child->nodeName, self::BOOLEAN_FIELD, true)) {
                $values[$child->nodeName] = $child->nodeValue === 'true';

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
