<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\Gateway;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class Gateways extends XmlElement
{
    /**
     * @var array<string, class-string<AbstractGateway>>
     */
    private const GATEWAYS = [
        'checkout' => CheckoutGateway::class,
        'context' => ContextGateway::class,
        'inAppPurchases' => InAppPurchasesGateway::class,
    ];

    protected ?CheckoutGateway $checkout = null;

    protected ?ContextGateway $context = null;

    protected ?InAppPurchasesGateway $inAppPurchases = null;

    public function getCheckout(): ?CheckoutGateway
    {
        return $this->checkout;
    }

    public function getContext(): ?ContextGateway
    {
        return $this->context;
    }

    public function getInAppPurchasesGateway(): ?InAppPurchasesGateway
    {
        return $this->inAppPurchases;
    }

    /**
     * @return array<string, AbstractGateway>
     */
    protected static function parse(\DOMElement $element): array
    {
        $gateways = [];

        foreach (self::GATEWAYS as $tagName => $gatewayClass) {
            $targetElement = $element->getElementsByTagName($tagName)->item(0);
            if ($targetElement !== null) {
                $gateways[$tagName] = $gatewayClass::fromXml($targetElement);
            }
        }

        return $gateways;
    }
}
