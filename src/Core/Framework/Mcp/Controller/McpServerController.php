<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Controller;

use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Request\GetPromptRequest;
use Mcp\Schema\Request\InitializeRequest;
use Mcp\Schema\Request\ReadResourceRequest;
use Mcp\Server;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlist;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlistFilter;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlistProvider;
use Shopware\Core\Framework\Mcp\Http\McpHttpTransportFactory;
use Shopware\Core\Framework\Mcp\McpJsonRpcResponse;
use Shopware\Core\Framework\Mcp\McpToolsetRegistry;
use Shopware\Core\Framework\Mcp\Notification\McpListChangedNotificationSet;
use Shopware\Core\Framework\Mcp\Notification\McpListChangedNotifier;
use Shopware\Core\Framework\Mcp\Notification\McpSessionRegistry;
use Shopware\Core\Framework\Mcp\RateLimit\McpRateLimiter;
use Shopware\Core\Framework\Mcp\Session\McpSessionIdValidator;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @experimental stableVersion:v6.8.0
 *
 * Shopware-aware entry point for the MCP protocol over HTTP.
 * Applies Shopware's Admin API authentication and route scoping, then delegates
 * the actual protocol handling to the Symfony MCP Server.
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class McpServerController
{
    public const ATTRIBUTE_JSONRPC_BODY = 'mcp._jsonrpc_body';
    private const TOOL_SEARCH = 'shopware-tool-search';

    /**
     * Server-owned discovery meta-tools. A tools/call for one of these is never rejected by the
     * per-integration allowlist, so a restricted integration can always reach the guaranteed
     * discovery path (toolsets-list -> toolset-enable -> listChanged).
     */
    private const DISCOVERY_META_TOOLS = [
        self::TOOL_SEARCH,
        McpToolsetRegistry::LIST_TOOLSETS_TOOL,
        McpToolsetRegistry::ENABLE_TOOLSET_TOOL,
    ];

    /**
     * @internal
     *
     * $server is nullable because it is injected via nullOnInvalid(): when the MCP bundle is absent
     * it resolves to null (as do the HTTP factories the transport factory wraps). Once MCP is stable
     * (v6.8.0) the nullable type and the null guard in handle() can be removed.
     */
    public function __construct(
        private readonly ?Server $server,
        private readonly McpHttpTransportFactory $transportFactory,
        private readonly McpRateLimiter $rateLimiter,
        private readonly McpSessionIdValidator $sessionIdValidator,
        private readonly ?McpAllowlistProvider $allowlistProvider = null,
        private readonly ?LoggerInterface $logger = null,
        private readonly McpAllowlistFilter $allowlistFilter = new McpAllowlistFilter(),
        private readonly ?McpSessionRegistry $sessionRegistry = null,
        private readonly ?McpListChangedNotifier $listChangedNotifier = null,
    ) {
    }

    #[Route(
        path: '/api/_mcp',
        name: 'api.mcp.endpoint',
        defaults: ['auth_required' => true],
        methods: [Request::METHOD_GET, Request::METHOD_POST, Request::METHOD_DELETE, Request::METHOD_OPTIONS],
    )]
    public function handle(Request $request): Response
    {
        if ($this->server === null || !$this->transportFactory->isAvailable()) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        }

        $this->sessionIdValidator->validate($request);
        $this->rateLimiter->enforceForAdminApi($request);

        $this->logger?->debug('MCP request', [
            'method' => $request->getMethod(),
            'clientIp' => $request->getClientIp(),
        ]);

        if ($request->getMethod() === 'POST') {
            $body = $this->decodeJson($request->getContent());
            if (\is_array($body)) {
                $request->attributes->set(self::ATTRIBUTE_JSONRPC_BODY, $body);
            }
        }

        $allowlist = $this->allowlistProvider?->forCurrentRequest();

        if ($allowlist !== null && $request->getMethod() === 'POST') {
            $earlyReject = $this->checkAllowlistEarlyReject($request, $allowlist);
            if ($earlyReject !== null) {
                return $earlyReject;
            }
        }

        $psrResponse = $this->server->run($this->transportFactory->createTransport($request));
        $this->registerSession($psrResponse);
        $this->flushPendingToolsListChanged($request);

        if ($request->getMethod() === 'POST') {
            $psrResponse = $this->enrichInitializeResponse($request, $psrResponse);
        }

        return $this->transportFactory->createResponse($psrResponse);
    }

    /**
     * Emits a tools/listChanged for the current session when a tool asked for it (e.g.
     * shopware-toolset-enable). This runs after {@see Server::run()} has persisted the SDK's
     * in-memory session, so the queued notification survives instead of being overwritten by the
     * SDK's own session save; the client drains it on its next poll.
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

    private function checkAllowlistEarlyReject(Request $request, McpAllowlist $allowlist): ?Response
    {
        $body = $this->decodeJson($request->getContent());

        if (!\is_array($body)) {
            return null;
        }

        $method = $body['method'] ?? null;

        if ($method === CallToolRequest::getMethod() && $allowlist->tools !== null) {
            $toolName = $body['params']['name'] ?? '';
            if (\in_array($toolName, self::DISCOVERY_META_TOOLS, true)) {
                return null;
            }

            if ($this->allowlistFilter->isToolCallDenied($toolName, $allowlist->tools)) {
                return $this->jsonRpcError(
                    $body['id'] ?? null,
                    $toolName !== ''
                        ? \sprintf('Tool "%s" is not enabled in your MCP allowlist.', $toolName)
                        : 'Tool call rejected: no tool name provided.',
                );
            }
        }

        if ($method === ReadResourceRequest::getMethod() && $allowlist->resources !== null) {
            $resourceUri = $body['params']['uri'] ?? '';
            if ($this->allowlistFilter->isResourceReadDenied($resourceUri, $allowlist->resources)) {
                return $this->jsonRpcError(
                    $body['id'] ?? null,
                    $resourceUri !== ''
                        ? \sprintf('Resource "%s" is not enabled in your MCP allowlist.', $resourceUri)
                        : 'Resource read rejected: no URI provided.',
                );
            }
        }

        if ($method === GetPromptRequest::getMethod() && $allowlist->prompts !== null) {
            $promptName = $body['params']['name'] ?? '';
            if ($this->allowlistFilter->isPromptGetDenied($promptName, $allowlist->prompts)) {
                return $this->jsonRpcError(
                    $body['id'] ?? null,
                    $promptName !== ''
                        ? \sprintf('Prompt "%s" is not enabled in your MCP allowlist.', $promptName)
                        : 'Prompt get rejected: no prompt name provided.',
                );
            }
        }

        return null;
    }

    private function enrichInitializeResponse(Request $request, PsrResponseInterface $psrResponse): PsrResponseInterface
    {
        $body = $this->decodeJson($request->getContent());

        if (!\is_array($body) || ($body['method'] ?? null) !== InitializeRequest::getMethod()) {
            return $psrResponse;
        }

        $response = McpJsonRpcResponse::fromJson((string) $psrResponse->getBody());

        if ($response === null) {
            return $psrResponse;
        }

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        if (!$context instanceof Context) {
            return $psrResponse;
        }

        $source = $context->getSource();
        if (!$source instanceof AdminApiSource) {
            return $psrResponse;
        }

        if (!$response->addShopwareMeta($source->getUserId(), $source->getIntegrationId())) {
            return $psrResponse;
        }

        $newBody = Json::encode($response);
        $newStream = $this->transportFactory->createStream($newBody);

        return $psrResponse
            ->withBody($newStream)
            ->withHeader('Content-Length', (string) \strlen($newBody));
    }

    private function jsonRpcError(mixed $id, string $message): Response
    {
        $payload = Json::encode([
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => -32001,
                'message' => $message,
            ],
        ]);

        return new Response($payload, Response::HTTP_OK, ['Content-Type' => 'application/json']);
    }

    private function decodeJson(string $content): mixed
    {
        try {
            return json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
    }
}
