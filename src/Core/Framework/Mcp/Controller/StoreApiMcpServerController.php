<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Controller;

use Mcp\Server;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Http\McpHttpTransportFactory;
use Shopware\Core\Framework\Mcp\Notification\McpListChangedNotificationSet;
use Shopware\Core\Framework\Mcp\Notification\McpListChangedNotifier;
use Shopware\Core\Framework\Mcp\Notification\McpSessionRegistry;
use Shopware\Core\Framework\Mcp\RateLimit\McpRateLimiter;
use Shopware\Core\Framework\Mcp\Session\McpSessionIdValidator;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
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
     * $server is nullable because it is injected via nullOnInvalid(): when the MCP bundle is absent
     * it resolves to null (as do the HTTP factories the transport factory wraps). Once MCP is
     * stable (v6.8.0) remove the nullable type and the null guard in handle().
     */
    public function __construct(
        private readonly ?Server $server,
        private readonly McpHttpTransportFactory $transportFactory,
        private readonly McpRateLimiter $rateLimiter,
        private readonly McpSessionIdValidator $sessionIdValidator,
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
        if ($this->server === null || !$this->transportFactory->isAvailable()) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        }

        $this->sessionIdValidator->validate($request);
        $this->rateLimiter->enforceForStoreApi($request);

        $this->logger?->debug('Store API MCP request', [
            'method' => $request->getMethod(),
            'clientIp' => $request->getClientIp(),
        ]);

        $psrResponse = $this->server->run($this->transportFactory->createTransport($request));
        $this->registerSession($psrResponse);
        $this->flushPendingToolsListChanged($request);

        return $this->transportFactory->createResponse($psrResponse);
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
}
