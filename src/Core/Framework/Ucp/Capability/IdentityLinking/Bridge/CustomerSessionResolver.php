<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Bridge;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Resolves the currently logged-in storefront customer from the request.
 *
 * Strategy:
 *   1. If the request carries a `sw-context-token` header (Store API style),
 *      we use it directly via the SalesChannelContextFactory.
 *   2. If the request carries a `session` cookie (storefront-style),
 *      we read the context token from the session payload through Shopware's
 *      Symfony session bag.
 *
 * Returns the customer id (hex) or null if no active customer.
 *
 * @internal
 */
#[Package('framework')]
class CustomerSessionResolver
{
    public function __construct(
        private readonly AbstractSalesChannelContextFactory $contextFactory,
    ) {
    }

    public function getActiveCustomerId(Request $request): ?string
    {
        // Preferred: Shopware's Storefront RequestTransformer has already attached
        // a SalesChannelContext to the request — pick up the customer from there.
        $existing = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
        if ($existing instanceof SalesChannelContext) {
            $customer = $existing->getCustomer();
            if ($customer !== null) {
                return $customer->getId();
            }
        }

        // Fallback: build a context from session / explicit token.
        $domain = $request->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_ID);
        $salesChannelId = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID);
        $contextToken = $this->resolveContextToken($request);

        if (!\is_string($salesChannelId)) {
            return null;
        }
        if ($contextToken === null) {
            return null;
        }

        $params = array_filter([
            SalesChannelContextService::DOMAIN_ID => $domain,
        ]);
        $context = $this->contextFactory->create($contextToken, $salesChannelId, $params);
        $customer = $context->getCustomer();

        return $customer?->getId();
    }

    private function resolveContextToken(Request $request): ?string
    {
        $explicit = $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        if (\is_string($explicit) && $explicit !== '') {
            return $explicit;
        }

        $session = $request->getSession();
        $token = $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN);

        return \is_string($token) && $token !== '' ? $token : null;
    }
}
