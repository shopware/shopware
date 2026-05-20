<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Controller;

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
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlistFilter;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlistProvider;
use Shopware\Core\Framework\Mcp\McpException;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
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
#[Route(defaults: ['_routeScope' => ['api']])]
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
        private readonly RateLimiter $rateLimiter,
        private readonly ?McpAllowlistProvider $allowlistProvider = null,
        private readonly ?LoggerInterface $logger = null,
        private readonly McpAllowlistFilter $allowlistFilter = new McpAllowlistFilter(),
    ) {
    }

    #[Route(path: '/api/_mcp', name: 'api.mcp.endpoint', defaults: ['auth_required' => true], methods: ['GET', 'POST', 'DELETE', 'OPTIONS'])]
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

        $this->rateLimit($request);

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

    /**
     * @param array{tools: list<string>|null, resources: list<string>|null, prompts: list<string>|null} $allowlist
     */
    private function checkAllowlistEarlyReject(Request $request, array $allowlist): ?Response
    {
        $body = $this->decodeJson($request->getContent());

        if (!\is_array($body)) {
            return null;
        }

        $method = $body['method'] ?? null;

        if ($method === 'tools/call' && $allowlist[McpAllowlistProvider::TOOLS] !== null) {
            $toolName = $body['params']['name'] ?? '';
            if ($this->allowlistFilter->isToolCallDenied($toolName, $allowlist[McpAllowlistProvider::TOOLS])) {
                return $this->jsonRpcError(
                    $body['id'] ?? null,
                    $toolName !== ''
                        ? \sprintf('Tool "%s" is not enabled in your MCP allowlist.', $toolName)
                        : 'Tool call rejected: no tool name provided.',
                );
            }
        }

        if ($method === 'resources/read' && $allowlist[McpAllowlistProvider::RESOURCES] !== null) {
            $resourceUri = $body['params']['uri'] ?? '';
            if ($this->allowlistFilter->isResourceReadDenied($resourceUri, $allowlist[McpAllowlistProvider::RESOURCES])) {
                return $this->jsonRpcError(
                    $body['id'] ?? null,
                    $resourceUri !== ''
                        ? \sprintf('Resource "%s" is not enabled in your MCP allowlist.', $resourceUri)
                        : 'Resource read rejected: no URI provided.',
                );
            }
        }

        if ($method === 'prompts/get' && $allowlist[McpAllowlistProvider::PROMPTS] !== null) {
            $promptName = $body['params']['name'] ?? '';
            if ($this->allowlistFilter->isPromptGetDenied($promptName, $allowlist[McpAllowlistProvider::PROMPTS])) {
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

    /**
     * @param array{tools: list<string>|null, resources: list<string>|null, prompts: list<string>|null} $allowlist
     */
    private function filterListResponse(Request $request, PsrResponseInterface $psrResponse, array $allowlist): PsrResponseInterface
    {
        \assert($this->streamFactory !== null);

        $body = $this->decodeJson($request->getContent());

        if (!\is_array($body)) {
            return $psrResponse;
        }

        $method = $body['method'] ?? null;

        $hasFilter = match ($method) {
            'tools/list' => $allowlist[McpAllowlistProvider::TOOLS] !== null,
            'resources/list' => $allowlist[McpAllowlistProvider::RESOURCES] !== null,
            'prompts/list' => $allowlist[McpAllowlistProvider::PROMPTS] !== null,
            default => false,
        };

        if (!$hasFilter) {
            return $psrResponse;
        }

        $contentType = $psrResponse->getHeaderLine('Content-Type');
        if (!str_starts_with($contentType, 'application/json')) { // @codeCoverageIgnore
            return $psrResponse; // @codeCoverageIgnore
        }

        $responseData = $this->decodeJson((string) $psrResponse->getBody(), false);

        if (!$responseData instanceof \stdClass) { // @codeCoverageIgnore
            return $psrResponse; // @codeCoverageIgnore
        }

        if ($method === 'tools/list' && $allowlist[McpAllowlistProvider::TOOLS] !== null) {
            $responseData = $this->allowlistFilter->filterToolsListResponse($responseData, $allowlist[McpAllowlistProvider::TOOLS]);
        } elseif ($method === 'resources/list' && $allowlist[McpAllowlistProvider::RESOURCES] !== null) {
            $responseData = $this->allowlistFilter->filterResourcesListResponse($responseData, $allowlist[McpAllowlistProvider::RESOURCES]);
        } elseif ($method === 'prompts/list' && $allowlist[McpAllowlistProvider::PROMPTS] !== null) {
            $responseData = $this->allowlistFilter->filterPromptsListResponse($responseData, $allowlist[McpAllowlistProvider::PROMPTS]);
        }

        $newBody = json_encode($responseData, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
        $newStream = $this->streamFactory->createStream($newBody);

        return $psrResponse
            ->withBody($newStream)
            ->withHeader('Content-Length', (string) \strlen($newBody));
    }

    private function enrichInitializeResponse(Request $request, PsrResponseInterface $psrResponse): PsrResponseInterface
    {
        \assert($this->streamFactory !== null);

        $body = $this->decodeJson($request->getContent());

        if (!\is_array($body) || ($body['method'] ?? null) !== 'initialize') {
            return $psrResponse;
        }

        $contentType = $psrResponse->getHeaderLine('Content-Type');
        if (!str_starts_with($contentType, 'application/json')) {
            return $psrResponse;
        }

        $responseData = $this->decodeJson((string) $psrResponse->getBody(), false);
        if (!$responseData instanceof \stdClass) {
            return $psrResponse;
        }

        /** @var Context|null $context */
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        if ($context === null) {
            return $psrResponse;
        }

        $source = $context->getSource();
        if (!$source instanceof AdminApiSource) {
            return $psrResponse;
        }

        $shopwareMeta = new \stdClass();
        if ($source->getUserId() !== null) {
            $shopwareMeta->user = (object) ['id' => $source->getUserId()];
        }
        if ($source->getIntegrationId() !== null) {
            $shopwareMeta->integration = (object) ['id' => $source->getIntegrationId()];
        }

        if (!isset($shopwareMeta->user) && !isset($shopwareMeta->integration)) {
            return $psrResponse;
        }

        $result = $responseData->result ?? null;
        if (!$result instanceof \stdClass) {
            return $psrResponse;
        }

        if (!isset($result->_meta) || !$result->_meta instanceof \stdClass) {
            $result->_meta = new \stdClass();
        }

        if (!isset($result->_meta->shopware) || !$result->_meta->shopware instanceof \stdClass) {
            $result->_meta->shopware = new \stdClass();
        }

        if (isset($shopwareMeta->user)) {
            $result->_meta->shopware->user = $shopwareMeta->user;
        }
        if (isset($shopwareMeta->integration)) {
            $result->_meta->shopware->integration = $shopwareMeta->integration;
        }

        $newBody = json_encode($responseData, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
        $newStream = $this->streamFactory->createStream($newBody);

        return $psrResponse
            ->withBody($newStream)
            ->withHeader('Content-Length', (string) \strlen($newBody));
    }

    private function jsonRpcError(mixed $id, string $message): Response
    {
        $payload = json_encode([
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => -32001,
                'message' => $message,
            ],
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);

        return new Response($payload, Response::HTTP_OK, ['Content-Type' => 'application/json']);
    }

    private function decodeJson(string $content, bool $associative = true): mixed
    {
        try {
            return json_decode($content, $associative, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) { // @codeCoverageIgnore
            return null; // @codeCoverageIgnore
        }
    }

    private function rateLimit(Request $request): void
    {
        $key = $request->attributes->getString(PlatformRequest::ATTRIBUTE_OAUTH_ACCESS_TOKEN_ID)
            ?: $request->getClientIp()
            ?: 'unknown';

        try {
            $this->rateLimiter->ensureAccepted(RateLimiter::MCP, $key);
        } catch (RateLimitExceededException $e) {
            throw McpException::throttled($e->getWaitTime(), $e);
        }
    }
}
