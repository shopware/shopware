<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\ShippingMethod;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class ShippingMethods extends XmlElement
{
    /**
     * @var list<ShippingMethod>
     */
    protected array $shippingMethods;

    /**
     * @return list<ShippingMethod>
     */
    public function getShippingMethods(): array
    {
        return $this->shippingMethods;
    }

    protected static function parse(\DOMElement $element): array
    {
        $shippingMethods = [];
        foreach ($element->getElementsByTagName('shipping-method') as $shippingMethod) {
            $shippingMethods[] = ShippingMethod::fromXml($shippingMethod);
        }

        return ['shippingMethods' => $shippingMethods];
    }
}
