<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\ShippingMethod;

use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\App\Manifest\XmlParserUtils;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class ShippingMethod extends XmlElement
{
    protected const REQUIRED_FIELDS = [
        'identifier',
        'name',
        'deliveryTime',
    ];

    private const TRANSLATABLE_FIELDS = [
        'name',
        'description',
        'tracking-url',
    ];

    protected string $identifier;

    /**
     * @var array<string, string>
     */
    protected array $name;

    /**
     * @var array<string, string>
     */
    protected array $description = [];

    protected ?string $icon = null;

    protected int $position = ShippingMethodEntity::POSITION_DEFAULT;

    protected bool $active;

    /**
     * @var array<string, string>
     */
    protected array $trackingUrl = [];

    protected DeliveryTime $deliveryTime;

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

        $data['appShippingMethod'] = [
            'identifier' => $data['identifier'],
        ];

        unset($data['identifier']);

        if (\array_key_exists('deliveryTime', $data) && $data['deliveryTime'] instanceof DeliveryTime) {
            $data['deliveryTime'] = $data['deliveryTime']->toArray($defaultLocale);
        }

        return $data;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * @return array<string, string>
     */
    public function getName(): array
    {
        return $this->name;
    }

    /**
     * @return array<string, string>
     */
    public function getDescription(): array
    {
        return $this->description;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    /**
     * @return array<string, string>
     */
    public function getTrackingUrl(): array
    {
        return $this->trackingUrl;
    }

    public function getDeliveryTime(): DeliveryTime
    {
        return $this->deliveryTime;
    }

    protected static function parse(\DOMElement $element): array
    {
        $values = [];

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if (\in_array($child->tagName, self::TRANSLATABLE_FIELDS, true)) {
                $values = XmlParserUtils::mapTranslatedTag($child, $values);

                continue;
            }

            if ($child->tagName === 'delivery-time') {
                $values[XmlParserUtils::kebabCaseToCamelCase($child->tagName)] = DeliveryTime::fromXml($child);

                continue;
            }

            $values[XmlParserUtils::kebabCaseToCamelCase($child->tagName)] = XmlUtils::phpize((string) $child->nodeValue);
        }

        return $values;
    }
}
