<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\Loyalty;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\UcpException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Collects loyalty data from all installed providers and produces the
 * `loyalty[]` array UCP carries on Catalog / Cart / Checkout / Order responses
 * per `ucp/docs/specification/loyalty.md`.
 *
 * Each provider is responsible for **one** membership namespace
 * (`com.acme.loyalty`, `com.shopware.points`, …) so multi-provider setups
 * (e.g. a coalition + a merchant program) can coexist without colliding.
 *
 * If no providers are installed (the default), `aggregate()` returns an empty
 * list — clients then see no `loyalty` field at all, which is the documented
 * "loyalty inactive" state.
 *
 * @internal
 */
#[Package('framework')]
class LoyaltyAggregator
{
    /**
     * @param iterable<LoyaltyProviderInterface> $providers
     */
    public function __construct(
        private readonly iterable $providers,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function aggregate(SalesChannelContext $context): array
    {
        $out = [];
        $seenNamespaces = [];

        foreach ($this->providers as $provider) {
            $namespace = $provider->getMembershipNamespace();
            if (isset($seenNamespaces[$namespace])) {
                // Last writer would otherwise win silently — be loud about it.
                throw UcpException::loyaltyProviderError(\sprintf(
                    'multiple providers claim namespace "%s" — each membership namespace must have exactly one provider',
                    $namespace
                ));
            }
            $seenNamespaces[$namespace] = true;

            $data = $provider->buildLoyaltyData($context);
            if ($data === null) {
                continue;
            }

            // Enforce the spec's required namespace field on the emitted entry.
            $data['namespace'] = $namespace;
            $out[] = $data;
        }

        return $out;
    }
}
