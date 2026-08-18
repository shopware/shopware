<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Writes a context token into the storefront session, using the same key layout that
 * {@see StorefrontSubscriber::startSession()} reads it back from.
 *
 * @internal
 */
#[Package('discovery')]
class ContextTokenSessionWriter
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public function write(string $token, bool $destroyOldSession = false): void
    {
        $mainRequest = $this->requestStack->getMainRequest();
        if (!$mainRequest) {
            return;
        }
        if (!$mainRequest->attributes->get(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST)) {
            return;
        }
        if (!\in_array(StorefrontRouteScope::ID, $mainRequest->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []), true)) {
            return;
        }

        // Storefront sessions are started during kernel.request, before customer login and logout events are dispatched.
        if (!$mainRequest->hasSession(true)) {
            return;
        }

        $session = $mainRequest->getSession();
        $session->migrate($destroyOldSession);
        $session->set('sessionId', $session->getId());

        // When customer binding is enabled, store tokens per sales channel
        $bindingEnabled = $this->systemConfigService->getBool('core.systemWideLoginRegistration.isCustomerBoundToSalesChannel');
        if ($bindingEnabled) {
            $salesChannelId = $mainRequest->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID);
            if ($salesChannelId) {
                $tokenKey = PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . $salesChannelId;
                $session->set($tokenKey, $token);
            }
        }

        // Always set the default key for backward compatibility
        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $token);
        $mainRequest->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $token);
    }
}
