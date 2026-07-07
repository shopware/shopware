<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Controller;

use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Request\GetPromptRequest;
use Mcp\Schema\Request\InitializeRequest;
use Mcp\Schema\Request\ListPromptsRequest;
use Mcp\Schema\Request\ListResourcesRequest;
use Mcp\Schema\Request\ListToolsRequest;
use Mcp\Schema\Request\ReadResourceRequest;
use Mcp\Server;
use Mcp\Server\Transport\StreamableHttpTransport;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlist;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlistFilter;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlistProvider;
use Shopware\Core\Framework\Mcp\McpJsonRpcResponse;
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
        private readonly ?McpAllowlistProvider $allowlistProvider = null,
        private readonly ?LoggerInterface $logger = null,
        private readonly McpAllowlistFilter $allowlistFilter = new McpAllowlistFilter(),
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
        );

        $psrResponse = $this->server->run($transport);

        if ($allowlist !== null && $request->getMethod() === 'POST') {
            $psrResponse = $this->filterListResponse($request, $psrResponse, $allowlist);
        }

        if ($request->getMethod() === 'POST') {
            $psrResponse = $this->enrichInitializeResponse($request, $psrResponse);
        }

        $streamed = strtolower($psrResponse->getHeaderLine('Content-Type')) === 'text/event-stream';

        return $this->httpFoundationFactory->createResponse($psrResponse, $streamed);
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

    private function filterListResponse(Request $request, PsrResponseInterface $psrResponse, McpAllowlist $allowlist): PsrResponseInterface
    {
        \assert($this->streamFactory !== null);

        $body = $this->decodeJson($request->getContent());

        if (!\is_array($body)) {
            return $psrResponse;
        }

        $method = \is_string($body['method'] ?? null) ? $body['method'] : null;

        if (!$this->hasListFilter($method, $allowlist)) {
            return $psrResponse;
        }

        $response = McpJsonRpcResponse::fromJson((string) $psrResponse->getBody());

        if ($response === null) {
            return $psrResponse;
        }

        $this->applyAllowlistFilter($response, $method, $allowlist);

        $newBody = Json::encode($response);
        $newStream = $this->streamFactory->createStream($newBody);

        return $psrResponse
            ->withBody($newStream)
            ->withHeader('Content-Length', (string) \strlen($newBody));
    }

    private function hasListFilter(?string $method, McpAllowlist $allowlist): bool
    {
        return ($method === ListToolsRequest::getMethod() && $allowlist->tools !== null)
            || ($method === ListResourcesRequest::getMethod() && $allowlist->resources !== null)
            || ($method === ListPromptsRequest::getMethod() && $allowlist->prompts !== null);
    }

    private function applyAllowlistFilter(McpJsonRpcResponse $response, ?string $method, McpAllowlist $allowlist): void
    {
        if ($method === ListToolsRequest::getMethod() && $allowlist->tools !== null) {
            $response->filterTools($allowlist->tools);
        } elseif ($method === ListResourcesRequest::getMethod() && $allowlist->resources !== null) {
            $response->filterResources($allowlist->resources);
        } elseif ($method === ListPromptsRequest::getMethod() && $allowlist->prompts !== null) {
            $response->filterPrompts($allowlist->prompts);
        }
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
