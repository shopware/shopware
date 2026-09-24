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
 * @experimental stableVersion:v6.8.0
 *
 * Wraps the core rate limiter for the MCP endpoints. The throttle handling is
 * shared, while the rate-limit key and the configured limits differ per API:
 * the Admin API keys on the OAuth access token, the Store API on the
 * sales-channel context plus a stable per-IP backstop.
 *
 * Handshake-era lifecycle methods ({@see self::HANDSHAKE_LIFECYCLE_METHODS}) are
 * exempt from the limiter. The MCP Streamable HTTP handshake requires
 * `initialize` then `notifications/initialized` back-to-back; Shopware's
 * `time_backoff` policy accepts only one request after a wait and would
 * otherwise make a standards-compliant handshake impossible once the first
 * threshold is crossed (see #18906). Tool calls and other methods stay limited.
 * The 2026-07-28 modern era has no such pair, so the exemption is handshake-only.
 */
#[Package('framework')]
class McpRateLimiter
{
    /**
     * JSON-RPC methods that form the mandatory handshake-era session setup.
     * Kept as a narrow allowlist so abuse protection stays in force for tools
     * and every other protocol method.
     *
     * @var list<string>
     */
    private const HANDSHAKE_LIFECYCLE_METHODS = [
        'initialize',
        'notifications/initialized',
    ];

    /**
     * @internal
     */
    public function __construct(private readonly RateLimiter $rateLimiter)
    {
    }

    public function enforceForAdminApi(Request $request): void
    {
        if ($this->isHandshakeLifecycleRequest($request)) {
            return;
        }

        $key = $request->attributes->getString(PlatformRequest::ATTRIBUTE_OAUTH_ACCESS_TOKEN_ID)
            ?: $request->getClientIp()
            ?: 'unknown';

        $this->enforce(RateLimiter::MCP_ADMIN_API, $key);
    }

    public function enforceForStoreApi(Request $request): void
    {
        if ($this->isHandshakeLifecycleRequest($request)) {
            return;
        }

        $salesChannelContext = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);

        // Per-context bucket: the primary limit, applied only when a sales-channel context is
        // present. Its key is the client-supplied context token, which is cheap to rotate, so it
        // cannot be the only protection.
        if ($salesChannelContext instanceof SalesChannelContext) {
            $this->enforce(RateLimiter::MCP_STORE_API, $salesChannelContext->getSalesChannelId() . '-' . $salesChannelContext->getToken());
        }

        // Stable per-IP backstop on the same bucket: keyed on the client IP, it cannot be bypassed
        // by rotating the context token. Route + key form independent buckets, so this reuses the
        // mcp_store_api limits without a separate configuration.
        $this->enforce(RateLimiter::MCP_STORE_API, $request->getClientIp() ?: 'unknown');
    }

    /**
     * True when every JSON-RPC message in the POST body is a handshake lifecycle method.
     * Non-POST, unparseable, empty, or mixed batches still go through the limiter.
     */
    private function isHandshakeLifecycleRequest(Request $request): bool
    {
        if ($request->getMethod() !== Request::METHOD_POST) {
            return false;
        }

        $content = $request->getContent();
        if ($content === '') {
            return false;
        }

        try {
            $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return false;
        }

        if (!\is_array($decoded) || $decoded === []) {
            return false;
        }

        if (array_is_list($decoded)) {
            foreach ($decoded as $message) {
                if (!\is_array($message) || !$this->isHandshakeLifecycleMethod($message)) {
                    return false;
                }
            }

            return true;
        }

        return $this->isHandshakeLifecycleMethod($decoded);
    }

    /**
     * @param array<mixed> $message
     */
    private function isHandshakeLifecycleMethod(array $message): bool
    {
        $method = $message['method'] ?? null;

        return \is_string($method) && \in_array($method, self::HANDSHAKE_LIFECYCLE_METHODS, true);
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
