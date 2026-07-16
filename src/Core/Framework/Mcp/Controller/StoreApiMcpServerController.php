<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Controller;

use Mcp\Server;
use Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware;
use Mcp\Server\Transport\StreamableHttpTransport;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\McpAllowedHostsProvider;
use Shopware\Core\Framework\Mcp\Notification\McpListChangedNotificationSet;
use Shopware\Core\Framework\Mcp\Notification\McpListChangedNotifier;
use Shopware\Core\Framework\Mcp\Notification\McpSessionRegistry;
use Shopware\Core\Framework\Mcp\RateLimit\McpRateLimiter;
use Shopware\Core\Framework\Mcp\Session\McpSessionIdValidator;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\PlatformRequest;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
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
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
#[Package('framework')]
class StoreApiMcpServerController
{
    /**
     * @internal
     *
     * The first five params are nullable because they are injected via
     * nullOnInvalid(): when the MCP bundle is absent they resolve to null.
     * Once MCP_SERVER is stable (v6.8.0) remove the nullable types and the null guards in handle().
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
        private readonly ?McpSessionRegistry $sessionRegistry = null,
        private readonly ?McpListChangedNotifier $listChangedNotifier = null,
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
        if (!Feature::isActive('MCP_SERVER')
            || $this->server === null
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
        $this->registerSession($psrResponse);
        $this->flushPendingToolsListChanged($request);

        return $this->createHttpResponse($psrResponse);
    }

    /**
     * Converts the SDK's PSR response into a spec-compliant Symfony response. See
     * {@see McpServerController::createHttpResponse()}: an application/json body must be a single
     * JSON-RPC object, so a drained multi-message batch (e.g. a tools/list_changed alongside the
     * response) is re-emitted as a text/event-stream with one single-object SSE event per message.
     */
    private function createHttpResponse(PsrResponseInterface $psrResponse): Response
    {
        $contentType = strtolower($psrResponse->getHeaderLine('Content-Type'));

        if (str_starts_with($contentType, 'text/event-stream')) {
            \assert($this->httpFoundationFactory !== null);

            return $this->httpFoundationFactory->createResponse($psrResponse, true);
        }

        if (str_starts_with($contentType, 'application/json')) {
            $decoded = json_decode((string) $psrResponse->getBody(), true);

            if (\is_array($decoded) && array_is_list($decoded) && $decoded !== []) {
                return $this->eventStreamResponse($decoded, $psrResponse);
            }
        }

        \assert($this->httpFoundationFactory !== null);

        return $this->httpFoundationFactory->createResponse($psrResponse, false);
    }

    /**
     * @param list<mixed> $messages
     */
    private function eventStreamResponse(array $messages, PsrResponseInterface $psrResponse): Response
    {
        $body = '';
        foreach ($messages as $message) {
            $body .= 'event: message' . "\n" . 'data: ' . Json::encode($message) . "\n\n";
        }

        $response = new Response($body, $psrResponse->getStatusCode(), [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);

        $sessionId = $psrResponse->getHeaderLine(PlatformRequest::HEADER_MCP_SESSION_ID);
        if ($sessionId !== '') {
            $response->headers->set(PlatformRequest::HEADER_MCP_SESSION_ID, $sessionId);
        }

        return $response;
    }

    /**
     * Emits a tools/listChanged for the current store-api session when a tool asked for it (e.g.
     * shopware-toolset-enable). Runs after {@see Server::run()} has persisted the SDK session, so
     * the queued notification is not overwritten and the client drains it on its next poll.
     */
    private function flushPendingToolsListChanged(Request $request): void
    {
        if ($this->listChangedNotifier === null) {
            return;
        }

        if (!$request->attributes->getBoolean(McpListChangedNotifier::PENDING_TOOLS_LIST_CHANGED_ATTRIBUTE)) {
            return;
        }

        $sessionId = $request->headers->get('Mcp-Session-Id') ?? '';
        if ($sessionId === '') {
            return;
        }

        $this->listChangedNotifier->notifySession(
            $sessionId,
            new McpListChangedNotificationSet(tools: true, resources: false, prompts: false),
        );
    }

    /**
     * Registers the MCP session id emitted on the initialize response so the store-api
     * listChanged notifier can target this session (mirrors the Admin controller).
     */
    private function registerSession(PsrResponseInterface $psrResponse): void
    {
        if ($this->sessionRegistry === null) {
            return;
        }

        $sessionId = $psrResponse->getHeaderLine(PlatformRequest::HEADER_MCP_SESSION_ID);
        if ($sessionId === '') {
            return;
        }

        $this->sessionRegistry->register($sessionId);
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
