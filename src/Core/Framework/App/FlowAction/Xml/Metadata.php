<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\FlowAction\Xml;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @deprecated tag:v6.6.0 - Will be move to Shopware\Core\Framework\App\Flow\Action\Xml
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
     * @return array<string, mixed>
     */
    public function getLabel(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', '\Shopware\Core\Framework\App\Flow\Action\Xml\Metadata')
        );

        return $this->label;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getDescription(): ?array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', '\Shopware\Core\Framework\App\Flow\Action\Xml\Metadata')
        );

        return $this->description;
    }

    public function getName(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', '\Shopware\Core\Framework\App\Flow\Action\Xml\Metadata')
        );

        return $this->name;
    }

    public function getUrl(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', '\Shopware\Core\Framework\App\Flow\Action\Xml\Metadata')
        );

        return $this->url;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRequirements(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', '\Shopware\Core\Framework\App\Flow\Action\Xml\Metadata')
        );

        return $this->requirements;
    }

    public function getIcon(): ?string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', '\Shopware\Core\Framework\App\Flow\Action\Xml\Metadata')
        );

        return $this->icon;
    }

    public function getSwIcon(): ?string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', '\Shopware\Core\Framework\App\Flow\Action\Xml\Metadata')
        );

        return $this->swIcon;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getHeadline(): ?array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', '\Shopware\Core\Framework\App\Flow\Action\Xml\Metadata')
        );

        return $this->headline;
    }

    public function getDelayable(): bool
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', '\Shopware\Core\Framework\App\Flow\Action\Xml\Metadata')
        );

        return $this->delayable;
    }

    public function setDelayable(bool $delayable = false): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', '\Shopware\Core\Framework\App\Flow\Action\Xml\Metadata')
        );

        $this->delayable = $delayable;
    }

    public static function fromXml(\DOMElement $element): static
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', '\Shopware\Core\Framework\App\Flow\Action\Xml\Metadata')
        );

        return parent::fromXml($element);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(string $defaultLocale): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', '\Shopware\Core\Framework\App\Flow\Action\Xml\Metadata')
        );

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

    protected static function parse(\DOMElement $element): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', '\Shopware\Core\Framework\App\Flow\Action\Xml\Metadata')
        );

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
