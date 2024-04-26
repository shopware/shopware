<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Flow\Action\Xml;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\App\Manifest\XmlParserUtils;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class Metadata extends XmlElement
{
    protected const REQUIRED_FIELDS = [
        'label',
        'name',
        'url',
    ];
    private const TRANSLATABLE_FIELDS = [
        'label',
        'description',
        'headline',
    ];

    private const BOOLEAN_FIELD = ['delayable'];

    /**
     * @var array<string, string>
     */
    protected array $label;

    /**
     * @var array<string, string>|null
     */
    protected ?array $description = null;

    protected string $name;

    protected string $url;

    /**
     * @var list<string>
     */
    protected array $requirements = [];

    protected ?string $icon = null;

    protected ?string $swIcon = null;

    /**
     * @var array<string, string>|null
     */
    protected ?array $headline = null;

    protected bool $delayable = false;

    protected ?string $badge = null;

    /**
     * @return array<string, string>
     */
    public function getLabel(): array
    {
        return $this->label;
    }

    /**
     * @return array<string, string>|null
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
     * @return list<string>
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
     * @return array<string, string>|null
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

    public function getBadge(): ?string
    {
        return $this->badge;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(string $defaultLocale): array
    {
        $data = parent::toArray($defaultLocale);

        foreach (self::TRANSLATABLE_FIELDS as $field) {
            $translatableField = XmlParserUtils::kebabCaseToCamelCase($field);

            $data[$translatableField] = $this->ensureTranslationForDefaultLanguageExist(
                $data[$translatableField],
                $defaultLocale
            );
        }

        return $data;
    }

    protected static function parse(\DOMElement $element): array
    {
        $values = [];

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
