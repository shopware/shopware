<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\Loyalty;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Opt-in interface implemented by Shopware loyalty plugins (e.g. "Shopware
 * Loyalty App", custom merchant plugins) to expose UCP `loyalty` data on
 * Catalog, Cart, Checkout and Order responses.
 *
 * Tag: `ucp.loyalty_provider`. Each plugin declares the membership it owns
 * via {@see getMembershipNamespace()} (e.g. `com.acme.loyalty`).
 *
 * The core ships a no-op default implementation so that the loyalty
 * extension is opt-in: a Sales Channel can enable
 * `dev.ucp.shopping.loyalty` without breaking when no provider is installed,
 * and merchants see no loyalty fields in responses until a provider is in
 * place.
 */
#[Package('framework')]
interface LoyaltyProviderInterface
{
    public function getMembershipNamespace(): string;

    /**
     * Build the per-customer loyalty snapshot to embed in a UCP response.
     *
     * @return array<string, mixed>|null returning null means "no loyalty data for this customer"
     */
    public function buildLoyaltyData(SalesChannelContext $context): ?array;
}
