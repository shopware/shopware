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
use Shopware\Core\Framework\Mcp\McpToolSchemaNormalizer;
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
     *
     * On top of that, tool schemas are normalized on every path — the plain JSON object, the batch
     * re-emitted as SSE, and a native SSE stream the SDK emits directly — so an empty
     * `properties` map never reaches a client as `[]` (see {@see McpToolSchemaNormalizer}).
     */
    public function createResponse(PsrResponseInterface $psrResponse): Response
    {
        \assert($this->httpFoundationFactory !== null);

        $contentType = strtolower($psrResponse->getHeaderLine('Content-Type'));

        if (str_starts_with($contentType, 'text/event-stream')) {
            // A tools/list can arrive as a native SSE stream — the SDK emits one when the client
            // accepts text/event-stream, which is exactly the shape a tools/list takes right after
            // a toolset-enable (it rides the notification channel). It bypasses the JSON branch
            // below, so normalize the JSON carried in each `data:` frame here too, leaving the SSE
            // framing untouched. Server::run() returns a fully materialized response, so the body
            // is finite and safe to read.
            $body = (string) $psrResponse->getBody();

            return $this->rebuildResponse(self::normalizeEventStreamBody($body) ?? $body, $psrResponse);
        }

        if (str_starts_with($contentType, 'application/json')) {
            $decoded = json_decode((string) $psrResponse->getBody(), true);

            if (\is_array($decoded) && array_is_list($decoded) && $decoded !== []) {
                return $this->eventStreamResponse($decoded, $psrResponse);
            }

            if (\is_array($decoded)) {
                $normalized = McpToolSchemaNormalizer::normalizeToolListResult($decoded);

                if ($normalized !== null) {
                    return $this->rebuildResponse(Json::encode($normalized), $psrResponse);
                }
            }
        }

        return $this->httpFoundationFactory->createResponse($psrResponse, false);
    }

    /**
     * Rewrites the JSON-RPC messages carried in an SSE body's `data:` frames, leaving the SSE
     * framing (`event:`, `id:`, blank lines) untouched so event ids stay intact for resumability.
     * Returns null when nothing changed. Multi-line `data:` payloads are left alone — the SDK emits
     * single-line JSON per frame (see {@see self::eventStreamResponse()}).
     */
    private static function normalizeEventStreamBody(string $body): ?string
    {
        $lines = explode("\n", $body);
        $changed = false;

        foreach ($lines as $index => $line) {
            if (!str_starts_with($line, 'data:')) {
                continue;
            }

            $prefixLength = str_starts_with($line, 'data: ') ? 6 : 5;
            $decoded = json_decode(substr($line, $prefixLength), true);

            if (!\is_array($decoded)) {
                continue;
            }

            $normalized = McpToolSchemaNormalizer::normalizeToolListResult($decoded);

            if ($normalized === null) {
                continue;
            }

            $lines[$index] = substr($line, 0, $prefixLength) . Json::encode($normalized);
            $changed = true;
        }

        return $changed ? implode("\n", $lines) : null;
    }

    /**
     * Re-emits a (re-encoded) body while preserving the SDK response's status code and all headers
     * (Content-Type, mcp-session-id, and any others). The body was rewritten, so a now-stale
     * Content-Length is dropped and left for Symfony to recompute.
     */
    private function rebuildResponse(string $body, PsrResponseInterface $psrResponse): Response
    {
        $response = new Response($body, $psrResponse->getStatusCode());

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
                $message = McpToolSchemaNormalizer::normalizeToolListResult($message) ?? $message;
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
