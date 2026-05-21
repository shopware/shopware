<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\Checkout;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity\UcpSalesChannelConfigEntity;
use Shopware\Core\Framework\Ucp\Transport\Rest\UcpResponseEnvelopeListener;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Builds `continue_url` values for handoff back to the buyer's storefront
 * journey. Honours per-channel override templates configured via the admin UI.
 *
 * Template syntax: `{cartToken}`, `{orderId}` etc. are substituted with the
 * matching context values.
 *
 * @internal
 */
#[Package('framework')]
class ContinueUrlBuilder
{
    public const KIND_CART = 'cart';
    public const KIND_CHECKOUT = 'checkout';
    public const KIND_CONFIRM = 'confirm';
    public const KIND_ORDER = 'order';

    /**
     * Build a "go to storefront and recover this UCP request" URL for error
     * paths. Used by {@see UcpResponseEnvelopeListener}
     * to satisfy overview.md §"Error Handling" — every 4xx response SHOULD
     * carry a `continue_url` so the buyer can finish the journey by hand.
     *
     * Returns null when the sales channel has no resolvable domain (e.g. the
     * caller hit a discovery endpoint before context resolution succeeded).
     */
    public function buildForRecovery(
        UcpSalesChannelConfigEntity $config,
        SalesChannelContext $salesChannelContext
    ): ?string {
        $domainUrl = $this->resolveDomainUrl($salesChannelContext);
        if ($domainUrl === '') {
            return null;
        }

        $template = $config->getContinueUrlTemplate();
        if ($template !== null && $template !== '') {
            return $this->renderTemplate($template, [
                'domainUrl' => $domainUrl,
                'cartToken' => '',
                'kind' => 'recovery',
            ]);
        }

        // Default recovery destination: home page; buyer can navigate from there.
        return rtrim($domainUrl, '/') . '/';
    }

    public function buildForCheckout(
        UcpSalesChannelConfigEntity $config,
        SalesChannelContext $salesChannelContext,
        string $cartToken,
        string $kind = self::KIND_CONFIRM
    ): string {
        $template = $config->getContinueUrlTemplate();
        $domainUrl = $this->resolveDomainUrl($salesChannelContext);

        if ($template !== null && $template !== '') {
            return $this->renderTemplate($template, [
                'domainUrl' => $domainUrl,
                'cartToken' => $cartToken,
                'kind' => $kind,
            ]);
        }

        $path = match ($kind) {
            self::KIND_CART => '/checkout/cart',
            self::KIND_CONFIRM, self::KIND_CHECKOUT => '/checkout/confirm',
            self::KIND_ORDER => '/account/order',
            default => '/',
        };

        return rtrim($domainUrl, '/') . $path . '?ucp_token=' . urlencode($cartToken);
    }

    /**
     * @param array<string, string> $vars
     */
    private function renderTemplate(string $template, array $vars): string
    {
        $search = array_map(static fn (string $k): string => '{' . $k . '}', array_keys($vars));

        return str_replace($search, array_values($vars), $template);
    }

    private function resolveDomainUrl(SalesChannelContext $context): string
    {
        $domain = $context->getDomainId();
        $salesChannel = $context->getSalesChannel();
        $domains = $salesChannel->getDomains();

        if ($domains !== null && $domain !== null) {
            $entity = $domains->get($domain);
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

        return '';
    }
}
