<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\Loyalty;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\AbstractUcpCapability;
use Shopware\Core\Framework\Ucp\Capability\Cart\CartCapability;
use Shopware\Core\Framework\Ucp\Capability\Catalog\CatalogSearchCapability;
use Shopware\Core\Framework\Ucp\Capability\Checkout\CheckoutCapability;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * UCP loyalty extension. Extends multiple capabilities (Catalog, Cart, Checkout).
 *
 * The actual loyalty data is supplied by plugins implementing
 * {@see LoyaltyProviderInterface}. Without a registered provider this
 * extension publishes itself but contributes no fields to responses.
 */
#[Package('framework')]
class LoyaltyExtension extends AbstractUcpCapability
{
    public const NAME = 'dev.ucp.shopping.loyalty';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSpecUrl(): string
    {
        // Loyalty is a Shopware-side extension capability — UCP itself only
        // mentions loyalty in the capability reference and has no standalone
        // `/specification/loyalty/` page. Point to the reference where it is
        // documented; will switch to `/specification/loyalty/` once upstream
        // publishes a dedicated page.
        return 'https://ucp.dev/specification/reference/';
    }

    public function getSchemaUrl(): string
    {
        return 'https://ucp.dev/' . $this->getVersion() . '/schemas/common/loyalty.json';
    }

    public function getExtends(): string|array|null
    {
        return [
            CatalogSearchCapability::NAME,
            CartCapability::NAME,
            CheckoutCapability::NAME,
        ];
    }
}
