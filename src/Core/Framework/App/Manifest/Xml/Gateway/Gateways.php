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
    private const GATEWAYS = [
        'checkout' => CheckoutGateway::class,
        'inAppPurchases' => InAppPurchasesGateway::class,
    ];

    protected ?CheckoutGateway $checkout = null;

    protected ?InAppPurchasesGateway $inAppPurchases = null;

    public function getCheckout(): ?CheckoutGateway
    {
        return $this->checkout;
    }

    public function getInAppPurchasesGateway(): ?InAppPurchasesGateway
    {
        return $this->inAppPurchases;
    }

    /**
     * @return array<'checkout'|'inAppPurchases', XmlElement>
     */
    protected static function parse(\DOMElement $element): array
    {
        $gateways = [];

        foreach (self::GATEWAYS as $tagName => $class) {
            $gateway = self::parseGatewayElement($tagName, $element, $class);
            if ($gateway !== null) {
                $gateways[$tagName] = $gateway;
            }
        }

        return $gateways;
    }

    private static function parseGatewayElement(string $name, \DOMElement $element, string $class): ?XmlElement
    {
        $targetElement = $element->getElementsByTagName($name)->item(0);
        if ($targetElement) {
            // @phpstan-ignore-next-line argument.type doesn't expect callable array
            return \call_user_func([$class, 'fromXml'], $targetElement);
        }

        return null;
    }
}
