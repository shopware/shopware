<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Controller;

use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Request\GetPromptRequest;
use Mcp\Schema\Request\InitializeRequest;
use Mcp\Schema\Request\ReadResourceRequest;
use Mcp\Server;
use Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware;
use Mcp\Server\Transport\StreamableHttpTransport;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlist;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlistFilter;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlistProvider;
use Shopware\Core\Framework\Mcp\McpAllowedHostsProvider;
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
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * Shopware-aware entry point for the MCP protocol over HTTP.
 * Applies Shopware's Admin API authentication and route scoping, then delegates
 * the actual protocol handling to the Symfony MCP Server.
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('framework')]
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
     * The five PhpMcp bundle params below are nullable because they are injected via
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

        if ($request->getMethod() === 'POST') {
            $psrResponse = $this->enrichInitializeResponse($request, $psrResponse);
        }

        return $this->createHttpResponse($psrResponse);
    }

    /**
     * Converts the SDK's PSR response into a spec-compliant Symfony response.
     *
     * The MCP Streamable HTTP transport requires an application/json body to be a single JSON-RPC
     * object (JSON-RPC batching was removed in 2025-06-18). When the SDK drains more than one queued
     * outgoing message (e.g. a tools/list_changed notification alongside the response) it bundles
     * them as a top-level JSON array over application/json, which conformant clients cannot parse.
     * Re-emit such a batch as a text/event-stream where each JSON-RPC message is its own
     * single-object SSE event, which is where server-initiated notifications belong.
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

    /**
     * The SDK's default {@see DnsRebindingProtectionMiddleware} only allows localhost, which
     * rejects every request that reaches Shopware through its configured hostname. Keep the
     * mitigation but seed it with the shop's own hosts (APP_URL + sales channel domains) so
     * legitimate Admin API clients pass while cross-origin rebinding attempts are still blocked.
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
        \assert($this->streamFactory !== null);

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
        $newStream = $this->streamFactory->createStream($newBody);

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
