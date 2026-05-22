<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\Checkout;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Builds the `permalink_url` UCP `CheckoutController::complete()` returns as
 * part of the order proof the platform agent surfaces to the buyer post-
 * purchase.
 *
 * The previous implementation hardcoded `http://localhost:8080/account/order/...`
 * for every response. For a real platform reading the response as production
 * proof, that meant the buyer saw a dev URL inside their conversation
 * UI — a trust-boundary regression that this builder closes by deriving the
 * URL from the resolved sales-channel domain instead.
 *
 * Falls back to `null` (caller omits the `permalink_url` field) when no
 * sales-channel domain is configured rather than emitting any misleading URL.
 * The historical `localhost:8080` form is preserved only when conformance
 * mode is explicitly enabled, because the upstream Python conformance suite
 * asserts on that exact value.
 *
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * @internal
 */
#[Package('framework')]
class OrderPermalinkBuilder
{
    private const CONFORMANCE_FALLBACK_URL = 'http://localhost:8080';

    public function build(SalesChannelContext $context, string $orderId, bool $conformanceMode): ?string
    {
        $base = $this->resolveDomainUrl($context);

        if ($base === null || $base === '') {
            if ($conformanceMode) {
                return self::CONFORMANCE_FALLBACK_URL . '/account/order/' . $orderId;
            }

            return null;
        }

        return rtrim($base, '/') . '/account/order/' . $orderId;
    }

    private function resolveDomainUrl(SalesChannelContext $context): ?string
    {
        $domainId = $context->getDomainId();
        $salesChannel = $context->getSalesChannel();
        $domains = $salesChannel->getDomains();

        if ($domains !== null && $domainId !== null) {
            $entity = $domains->get($domainId);
            if ($entity !== null) {
                return $entity->getUrl();
            }
        }

        if ($domains !== null && $domains->count() > 0) {
            $first = $domains->first();
            if ($first !== null) {
                return $first->getUrl();
            }
        }

        return null;
    }
}
