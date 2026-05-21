<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\BuyerConsent;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\AbstractUcpCapability;
use Shopware\Core\Framework\Ucp\Capability\Checkout\CheckoutCapability;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Buyer Consent Extension — exposes the CCPA/GDPR consent states on the
 * `buyer.consent` object within the checkout response.
 */
#[Package('framework')]
class BuyerConsentExtension extends AbstractUcpCapability
{
    public const NAME = 'dev.ucp.shopping.buyer_consent';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSpecUrl(): string
    {
        return 'https://ucp.dev/' . $this->getVersion() . '/specification/buyer-consent';
    }

    public function getSchemaUrl(): string
    {
        return 'https://ucp.dev/' . $this->getVersion() . '/schemas/shopping/buyer_consent.json';
    }

    public function getExtends(): string|array|null
    {
        return CheckoutCapability::NAME;
    }
}
