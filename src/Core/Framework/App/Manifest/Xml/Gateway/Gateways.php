<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\Gateway;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class Gateways extends XmlElement
{
    protected ?CheckoutGateway $checkout = null;

    public function getCheckout(): ?CheckoutGateway
    {
        return $this->checkout;
    }

    /**
     * @return array{checkout?: CheckoutGateway}
     */
    protected static function parse(\DOMElement $element): array
    {
        $gateways = [];

        $checkout = $element->getElementsByTagName('checkout')->item(0);
        if ($checkout) {
            $gateways['checkout'] = CheckoutGateway::fromXml($checkout);
        }

        return $gateways;
    }
}
