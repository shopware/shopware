<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\ShippingMethod;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\XmlReader;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class DeliveryTime extends XmlElement
{
    protected const REQUIRED_FIELDS = [
        'id',
        'min',
        'max',
        'unit',
    ];

    protected string $id;

    protected string $name;

    protected int $min;

    protected int $max;

    protected string $unit;

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getMin(): int
    {
        return $this->min;
    }

    public function getMax(): int
    {
        return $this->max;
    }

    public function getUnit(): string
    {
        return $this->unit;
    }

    protected static function parse(\DOMElement $element): array
    {
        $deliveryTimeArray = [];
        foreach ($element->childNodes as $childNode) {
            if (!$childNode instanceof \DOMElement) {
                continue;
            }

            $deliveryTimeArray[self::kebabCaseToCamelCase($childNode->tagName)] = XmlReader::phpize($childNode->nodeValue);
        }

        return $deliveryTimeArray;
    }
}
