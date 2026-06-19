<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\RateLimit;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\McpException;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * Wraps the core rate limiter for the MCP endpoints. The throttle handling is
 * shared, while the rate-limit key and the configured limits differ per API:
 * the Admin API keys on the OAuth access token, the Store API on the
 * sales-channel context. Both fall back to the client IP.
 */
#[Package('framework')]
class McpRateLimiter
{
    /**
     * @internal
     */
    public function __construct(private readonly RateLimiter $rateLimiter)
    {
    }

    public function enforceForAdminApi(Request $request): void
    {
        $key = $request->attributes->getString(PlatformRequest::ATTRIBUTE_OAUTH_ACCESS_TOKEN_ID)
            ?: $request->getClientIp()
            ?: 'unknown';

        $this->enforce(RateLimiter::MCP_ADMIN_API, $key);
    }

    public function enforceForStoreApi(Request $request): void
    {
        $salesChannelContext = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);

        $key = $salesChannelContext instanceof SalesChannelContext
            ? $salesChannelContext->getSalesChannelId() . '-' . $salesChannelContext->getToken()
            : ($request->getClientIp() ?: 'unknown');

        $this->enforce(RateLimiter::MCP_STORE_API, $key);
    }

    private function enforce(string $route, string $key): void
    {
        try {
            $this->rateLimiter->ensureAccepted($route, $key);
        } catch (RateLimitExceededException $e) {
            throw McpException::throttled($e->getWaitTime(), $e);
        }
    }
}
