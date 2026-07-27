<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Http;

use Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware;
use Mcp\Server\Transport\StreamableHttpTransport;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\McpAllowedHostsProvider;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\PlatformRequest;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 *
 * Shared HTTP glue for the Admin and Store API MCP controllers: builds the Streamable HTTP
 * transport (seeded with the shop's own hosts for DNS-rebinding protection) and converts the SDK's
 * PSR response into a spec-compliant Symfony response.
 *
 * The PhpMcp bundle factories are nullable because they are injected via nullOnInvalid(): when the
 * MCP bundle is absent they resolve to null. Callers must check {@see self::isAvailable()} before
 * building a transport. Once MCP is stable (v6.8.0) the nullable types can be removed.
 */
#[Package('framework')]
class McpHttpTransportFactory
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ?HttpMessageFactoryInterface $httpMessageFactory,
        private readonly ?ResponseFactoryInterface $responseFactory,
        private readonly ?StreamFactoryInterface $streamFactory,
        private readonly ?HttpFoundationFactoryInterface $httpFoundationFactory,
        private readonly McpAllowedHostsProvider $allowedHostsProvider,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->httpMessageFactory !== null
            && $this->responseFactory !== null
            && $this->streamFactory !== null
            && $this->httpFoundationFactory !== null;
    }

    public function createTransport(Request $request): StreamableHttpTransport
    {
        \assert($this->httpMessageFactory !== null && $this->responseFactory !== null && $this->streamFactory !== null);

        return new StreamableHttpTransport(
            $this->httpMessageFactory->createRequest($request),
            $this->responseFactory,
            $this->streamFactory,
            logger: $this->logger,
            middleware: $this->middleware(),
        );
    }

    public function createStream(string $content): StreamInterface
    {
        \assert($this->streamFactory !== null);

        return $this->streamFactory->createStream($content);
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
     *
     * This guards against mcp/sdk v0.6.0 (symfony/mcp-bundle v0.10.0) still emitting batch arrays.
     * Once the SDK stops bundling multiple messages over application/json, this becomes a no-op and
     * can be removed.
     */
    public function createResponse(PsrResponseInterface $psrResponse): Response
    {
        \assert($this->httpFoundationFactory !== null);

        $contentType = strtolower($psrResponse->getHeaderLine('Content-Type'));

        if (str_starts_with($contentType, 'text/event-stream')) {
            return $this->httpFoundationFactory->createResponse($psrResponse, true);
        }

        if (str_starts_with($contentType, 'application/json')) {
            $decoded = json_decode((string) $psrResponse->getBody(), true);

            if (\is_array($decoded) && array_is_list($decoded) && $decoded !== []) {
                return $this->eventStreamResponse($decoded, $psrResponse);
            }

            if (\is_array($decoded)) {
                $normalized = self::normalizeToolSchemas($decoded);

                if ($normalized !== null) {
                    return $this->jsonResponse($normalized, $psrResponse);
                }
            }
        }

        return $this->httpFoundationFactory->createResponse($psrResponse, false);
    }

    /**
     * Forces every empty JSON Schema `properties` map inside a tool to a JSON object; returns null
     * when nothing changed.
     *
     * A parameterless tool — or a nested object parameter with no members — has an empty properties
     * map, and PHP encodes an empty array as `[]`. JSON Schema requires an object there, so strict
     * clients reject the whole payload — OpenAI answers
     * `400 invalid_function_parameters: "[] is not of type 'object'"`. Because
     * `shopware-toolsets-list` is advertised in every session, one malformed tool breaks every
     * request such a client makes, not just calls to that tool.
     *
     * The SDK normalizes this in `Tool::fromArray()` but not in `Tool::jsonSerialize()`, so tools
     * discovered by reflection — all of Shopware's — reach the wire unnormalized. Normalizing at
     * this single funnel covers both endpoints and every response shape, and stays correct
     * whichever way a future SDK release goes. The walk is recursive, so nested object properties
     * and an `outputSchema` are covered too, not just the top-level `inputSchema.properties`.
     *
     * @param array<string, mixed> $message
     *
     * @return array<string, mixed>|null
     */
    private static function normalizeToolSchemas(array $message): ?array
    {
        if (!\is_array($message['result'] ?? null) || !\is_array($message['result']['tools'] ?? null)) {
            return null;
        }

        $changed = false;

        foreach ($message['result']['tools'] as $index => $tool) {
            if (!\is_array($tool)) {
                continue;
            }

            foreach (['inputSchema', 'outputSchema'] as $schemaKey) {
                if (!\is_array($tool[$schemaKey] ?? null)) {
                    continue;
                }

                $message['result']['tools'][$index][$schemaKey] = self::normalizeSchemaNode($tool[$schemaKey], $changed);
            }
        }

        return $changed ? $message : null;
    }

    /**
     * Walks a JSON Schema node and replaces every empty `properties` map (at any depth) with an
     * empty object, so it serializes as `{}` instead of `[]`. Nested schemas — property
     * definitions, `items`, `$defs`, etc. — are reached through the generic array branch.
     *
     * @param array<string, mixed> $node
     *
     * @return array<string, mixed>
     */
    private static function normalizeSchemaNode(array $node, bool &$changed): array
    {
        foreach ($node as $key => $value) {
            if ($key === 'properties' && $value === []) {
                $node[$key] = new \stdClass();
                $changed = true;

                continue;
            }

            if (\is_array($value)) {
                $node[$key] = self::normalizeSchemaNode($value, $changed);
            }
        }

        return $node;
    }

    /**
     * Re-emits a normalized message as JSON while preserving the SDK response's status code and
     * headers (Content-Type, mcp-session-id, and any others). The body is re-encoded, so a stale
     * Content-Length is dropped and left for Symfony to recompute.
     *
     * @param array<string, mixed> $message
     */
    private function jsonResponse(array $message, PsrResponseInterface $psrResponse): Response
    {
        $response = new Response(Json::encode($message), $psrResponse->getStatusCode());

        foreach ($psrResponse->getHeaders() as $name => $values) {
            $response->headers->set($name, $values);
        }

        $response->headers->remove('Content-Length');

        return $response;
    }

    /**
     * @param list<mixed> $messages
     */
    private function eventStreamResponse(array $messages, PsrResponseInterface $psrResponse): Response
    {
        $body = '';
        foreach ($messages as $message) {
            // The batched shape is exactly what a tools/list following a toolset-enable looks
            // like (the list_changed notification rides along), so it needs the same schema
            // normalization as the single-object path.
            if (\is_array($message)) {
                $message = self::normalizeToolSchemas($message) ?? $message;
            }

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
     * The SDK's default {@see DnsRebindingProtectionMiddleware} only allows localhost, which rejects
     * every request that reaches Shopware through its configured hostname. Keep the mitigation but
     * seed it with the shop's own hosts (APP_URL + sales channel domains) so legitimate clients pass
     * while cross-origin rebinding attempts are still blocked.
     *
     * @return list<MiddlewareInterface>
     */
    private function middleware(): array
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
