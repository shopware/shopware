<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Controller;

use Mcp\Server;
use Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware;
use Mcp\Server\Transport\StreamableHttpTransport;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\McpAllowedHostsProvider;
use Shopware\Core\Framework\Mcp\RateLimit\McpRateLimiter;
use Shopware\Core\Framework\Mcp\Session\McpSessionIdValidator;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @experimental stableVersion:v6.8.0
 *
 * Store API entry point for the MCP protocol over HTTP.
 * This endpoint uses the normal Store API sales-channel access key and
 * sales-channel context token instead of Admin API OAuth/integration keys.
 *
 * No per-integration allowlist is applied here. The Admin API MCP endpoint
 * restricts capabilities per integration/user via McpAllowlistProvider, but
 * the Store API is intentionally open: any authenticated sales-channel client
 * can access all registered Store API MCP capabilities. Fine-grained access
 * control at the sales-channel level is a deliberate future extension point.
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class StoreApiMcpServerController
{
    /**
     * @internal
     *
     * The first five params are nullable because they are injected via
     * nullOnInvalid(): when the MCP bundle is absent they resolve to null.
     * Once MCP is stable (v6.8.0) remove the nullable types and the null guards in handle().
     */
    public function __construct(
        private readonly ?Server $server,
        private readonly ?HttpMessageFactoryInterface $httpMessageFactory,
        private readonly ?HttpFoundationFactoryInterface $httpFoundationFactory,
        private readonly ?ResponseFactoryInterface $responseFactory,
        private readonly ?StreamFactoryInterface $streamFactory,
        private readonly McpRateLimiter $rateLimiter,
        private readonly McpSessionIdValidator $sessionIdValidator,
        private readonly McpAllowedHostsProvider $allowedHostsProvider,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    #[Route(
        path: '/store-api/_mcp',
        name: 'store-api.mcp.endpoint',
        defaults: ['auth_required' => true],
        methods: [Request::METHOD_GET, Request::METHOD_POST, Request::METHOD_DELETE, Request::METHOD_OPTIONS],
    )]
    public function handle(Request $request): Response
    {
        if ($this->server === null
            || $this->httpMessageFactory === null
            || $this->httpFoundationFactory === null
            || $this->responseFactory === null
            || $this->streamFactory === null
        ) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        }

        $this->sessionIdValidator->validate($request);
        $this->rateLimiter->enforceForStoreApi($request);

        $this->logger?->debug('Store API MCP request', [
            'method' => $request->getMethod(),
            'clientIp' => $request->getClientIp(),
        ]);

        $transport = new StreamableHttpTransport(
            $this->httpMessageFactory->createRequest($request),
            $this->responseFactory,
            $this->streamFactory,
            logger: $this->logger,
            middleware: $this->transportMiddleware(),
        );

        $psrResponse = $this->server->run($transport);
        $streamed = strtolower($psrResponse->getHeaderLine('Content-Type')) === 'text/event-stream';

        return $this->httpFoundationFactory->createResponse($psrResponse, $streamed);
    }

    /**
     * The SDK's default {@see DnsRebindingProtectionMiddleware} only allows localhost, which
     * rejects every request that reaches Shopware through its configured hostname. Keep the
     * mitigation but seed it with the shop's own hosts (APP_URL + sales channel domains) so
     * legitimate Store API clients pass while cross-origin rebinding attempts are still blocked.
     *
     * @return list<MiddlewareInterface>
     */
    private function transportMiddleware(): array
    {
        $allowedHosts = $this->allowedHostsProvider->getAllowedHosts();

        return array_map(
            static fn (MiddlewareInterface $middleware): MiddlewareInterface => $middleware instanceof DnsRebindingProtectionMiddleware
                ? new DnsRebindingProtectionMiddleware($allowedHosts)
                : $middleware,
            StreamableHttpTransport::defaultMiddleware(),
        );
    }
}
