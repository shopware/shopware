<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class ShippingMethods extends XmlElement
{
    /**
     * @param list<ShippingMethod> $shippingMethods
     */
    private function __construct(protected readonly array $shippingMethods)
    {
    }

    public static function fromXml(\DOMElement $element): self
    {
        return new self(self::parseShippingMethods($element));
    }

    /**
     * @return list<ShippingMethod>
     */
    public function getShippingMethods(): array
    {
        return $this->shippingMethods;
    }

    /**
     * @return list<ShippingMethod>
     */
    private static function parseShippingMethods(\DOMElement $element): array
    {
        $shippingMethods = [];
        foreach ($element->getElementsByTagName('shipping-method') as $shippingMethod) {
            $shippingMethods[] = ShippingMethod::fromXml($shippingMethod);
        }

        return $shippingMethods;
    }
}
