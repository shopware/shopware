<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Result;

use Mcp\Schema\Content\ResourceLink;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Result\CallToolResult;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\ToolResultCacheStorage;
use Shopware\Core\Framework\Util\Json;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 *
 * The one place that knows the MCP tool result format. It renders a {@see McpToolResult} as a
 * `CallToolResult`: `structuredContent` for the data, `isError` for failures, and a text block for
 * models, which many clients pass on instead of `structuredContent`.
 *
 * Until 6.8.0 the text block keeps the legacy `{"success": …}` string, so existing readers keep
 * working. With the `v6.8.0.0` feature flag the text carries the plain data (or the error message)
 * instead, followed by the summary and the metadata as blocks of their own.
 *
 * It also decides about size: a successful result whose rendered form exceeds the limit of the
 * negotiated revision is stored for the calling principal and sent as a link, whichever tool or app
 * produced it. Clients since 2025-06-18 get the plain data stored, older ones the legacy envelope, see
 * {@see McpToolResultStoredFormat}.
 */
#[Package('framework')]
class McpToolResultRenderer
{
    /**
     * The largest result sent inline, counted as the text blocks plus the encoded `structuredContent`.
     * Since 2025-06-18 the data is sent twice, so this is about 100 KB of data, the limit that applied to
     * the tool's string before the renderer measured the rendered result.
     */
    public const MAX_INLINE_SIZE = 200_000;

    /**
     * Revisions before 2025-06-18 have no `structuredContent` and get the data once, in the text block.
     */
    public const MAX_INLINE_SIZE_TEXT_ONLY = 100_000;

    private const META_PREFIX = 'shopware/';

    public function __construct(
        private readonly ClockInterface $clock,
        private readonly ToolResultCacheStorage $storage,
        private readonly RequestStack $requestStack,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param string|null $legacyText the string the tool returned, if it returned the legacy envelope;
     *                                kept as is so existing readers see exactly what they saw before. Goes
     *                                away with the legacy envelope in 6.8.0
     * @param CallToolRequest|null $call the call that produced the result; a stored result echoes its tool
     *                                   name and arguments, so the model can tell which call a link belongs to
     */
    public function render(McpToolResult $result, ProtocolVersion $protocolVersion, ?string $legacyText = null, ?CallToolRequest $call = null): CallToolResult
    {
        $rendered = $this->renderInline($result, $protocolVersion, $legacyText);
        if ($result->isError()) {
            return $rendered;
        }

        $size = $this->renderedSize($rendered);
        if ($size <= $this->inlineLimit($protocolVersion)) {
            return $rendered;
        }

        $request = $this->requestStack->getCurrentRequest();
        $principal = $request !== null ? McpToolResultPrincipal::fromRequest($request) : null;
        if ($request === null || $principal === null) {
            // Nobody could read a stored result back (CLI, tests, a Store API call without `sw-context-token`).
            return $rendered;
        }

        $format = $this->storedFormat($protocolVersion);
        $content = $format === McpToolResultStoredFormat::Spec ? Json::encode($result->data) : ($legacyText ?? $this->legacyEnvelope($result));
        // The pointer is signed for the principal, so it works without an MCP session. The session id is
        // only recorded for the cleanup when a handshake session ends.
        $pointer = $this->storage->storeFor($principal, $content, $request->headers->get('Mcp-Session-Id') ?? '', $format->value);

        $this->logger->debug('MCP tool result stored as resource (oversized)', [
            'tool' => $call?->name,
            'renderedSize' => $size,
            'resourceUri' => $pointer->uri(),
        ]);

        [$linked, $linkedLegacyText] = $this->linkedResult($result, $pointer, \strlen($content), $protocolVersion, $call);

        return $this->renderInline($linked, $protocolVersion, $linkedLegacyText);
    }

    private function renderInline(McpToolResult $result, ProtocolVersion $protocolVersion, ?string $legacyText): CallToolResult
    {
        $specOnly = Feature::isActive('v6.8.0.0');
        if ($specOnly) {
            $result = $this->withoutLinkedResourceUri($result, $protocolVersion);
        }
        $texts = $specOnly ? $this->specTexts($result) : [$legacyText ?? $this->legacyEnvelope($result)];

        return new CallToolResult(
            [
                ...array_map(static fn (string $text): TextContent => new TextContent($text), $texts),
                ...$this->links($result, $protocolVersion),
            ],
            isError: $result->isError(),
            structuredContent: $this->structuredContent($result, $protocolVersion),
            meta: $this->meta($result, $specOnly),
        );
    }

    /**
     * What the client and the model read: the text blocks as they are, plus `structuredContent` as JSON.
     * The escaping of the text inside the JSON-RPC response is left out, so the limit doesn't depend on
     * how many quotes the data has.
     */
    private function renderedSize(CallToolResult $result): int
    {
        $size = $result->structuredContent !== null ? \strlen(Json::encode($result->structuredContent)) : 0;
        foreach ($result->content as $block) {
            if ($block instanceof TextContent) {
                $size += \strlen((string) $block->text);
            }
        }

        return $size;
    }

    /**
     * The legacy envelope was Shopware's convention before MCP had `structuredContent` and `resource_link`.
     * Clients since 2025-06-18 read the stored data as it is and get its metadata with the link.
     */
    private function storedFormat(ProtocolVersion $protocolVersion): McpToolResultStoredFormat
    {
        return $protocolVersion->isAtLeast(ProtocolVersion::V2025_06_18) ? McpToolResultStoredFormat::Spec : McpToolResultStoredFormat::Legacy;
    }

    private function inlineLimit(ProtocolVersion $protocolVersion): int
    {
        return $protocolVersion->isAtLeast(ProtocolVersion::V2025_06_18) ? self::MAX_INLINE_SIZE : self::MAX_INLINE_SIZE_TEXT_ONLY;
    }

    /**
     * The result that replaces a stored one, and its legacy text. The legacy text is what
     * `McpToolResponse::success()` returned for a stored result before the renderer took over, so existing
     * readers keep finding the pointer in `_meta.resourceUri`. Goes away with the legacy envelope in 6.8.0.
     *
     * @return array{McpToolResult, string}
     */
    private function linkedResult(McpToolResult $result, McpToolResultPointer $pointer, int $storedSize, ProtocolVersion $protocolVersion, ?CallToolRequest $call): array
    {
        // With the 6.8 format, a client that gets the `resource_link` doesn't get `_meta.resourceUri`.
        $pointsToLink = Feature::isActive('v6.8.0.0') && $protocolVersion->isAtLeast(ProtocolVersion::V2025_06_18);
        $note = 'Response too large for inline delivery. '
            . 'Prefer re-running the tool with tighter "includes" or a lower "limit" to get a smaller inline result. '
            . ($pointsToLink
                ? 'If you need the full data as-is, fetch it via resources/read using the URI of the resource_link.'
                : 'If you need the full data as-is, fetch it via resources/read using the URI in _meta.resourceUri.');

        $meta = [...$result->meta, 'resourceUri' => $pointer->uri(), 'responseSize' => $storedSize];
        $legacyMeta = [
            ...$result->meta,
            'resourceUri' => $pointer->uri(),
            'expiresAt' => $pointer->expiresAt->format(\DateTimeInterface::ATOM),
            'responseSize' => $storedSize,
            'note' => $note,
        ];

        if ($call !== null && $call->name !== '') {
            $meta['query'] = $legacyMeta['query'] = ['tool' => $call->name, 'arguments' => $call->arguments];
        }

        $linked = new McpToolResult(
            summary: $note,
            meta: $meta,
            generatedAt: $result->generatedAt,
            expiresAt: $pointer->expiresAt,
            links: [new McpToolResultLink($pointer->uri(), 'tool-result', $note, 'application/json', $storedSize)],
        );

        return [$linked, Json::encode(['success' => true, 'data' => null, '_meta' => $legacyMeta])];
    }

    /**
     * Since 2025-06-18 the `resource_link` block carries the pointer, so the 6.8 format doesn't repeat it
     * in the metadata. Older revisions only read the text and keep it there.
     */
    private function withoutLinkedResourceUri(McpToolResult $result, ProtocolVersion $protocolVersion): McpToolResult
    {
        $uri = $result->meta['resourceUri'] ?? null;
        if (!\is_string($uri) || !$protocolVersion->isAtLeast(ProtocolVersion::V2025_06_18)) {
            return $result;
        }

        foreach ($result->links as $link) {
            if ($link->uri === $uri) {
                $meta = $result->meta;
                unset($meta['resourceUri']);

                return new McpToolResult($result->data, $result->summary, $result->error, $meta, $result->generatedAt, $result->expiresAt, $result->links);
            }
        }

        return $result;
    }

    /**
     * The legacy envelope that `McpToolResponse::success()` and `::error()` produce. Goes away with the
     * legacy envelope in 6.8.0.
     */
    private function legacyEnvelope(McpToolResult $result): string
    {
        if ($result->error !== null) {
            return Json::encode(['success' => false, 'error' => $result->error->message, 'code' => $result->error->code]);
        }

        $envelope = ['success' => true, 'data' => $result->data];
        if ($result->meta !== []) {
            $envelope['_meta'] = $result->meta;
        }

        return Json::encode($envelope);
    }

    /**
     * @return list<ResourceLink>
     */
    private function links(McpToolResult $result, ProtocolVersion $protocolVersion): array
    {
        // `resource_link` content blocks exist since 2025-06-18; older clients read the legacy text.
        if (!$protocolVersion->isAtLeast(ProtocolVersion::V2025_06_18)) {
            return [];
        }

        return array_map(static fn (McpToolResultLink $link): ResourceLink => new ResourceLink(
            $link->uri,
            $link->name,
            description: $link->description,
            mimeType: $link->mimeType,
            size: $link->size,
        ), $result->links);
    }

    /**
     * The text blocks of the spec-only format: the data as plain JSON first, then the summary, then
     * `{"_meta": …}`. A summary never replaces the data. A failure sends its message, then its details and
     * `{"_meta": …}`.
     *
     * @return non-empty-list<string>
     */
    private function specTexts(McpToolResult $result): array
    {
        if ($result->error !== null) {
            $texts = [$result->error->message];
            // The details, for example the violations of a rejected call, are what the model needs to fix it.
            if ($result->error->details !== []) {
                $texts[] = Json::encode(['details' => $result->error->details]);
            }
            if ($result->meta !== []) {
                $texts[] = Json::encode(['_meta' => $result->meta]);
            }

            return $texts;
        }

        $texts = [];
        // A result without data, such as one stored behind a link, has no `structuredContent` to copy.
        if ($result->data !== null) {
            $texts[] = Json::encode($result->data);
        }
        if ($result->summary !== null) {
            $texts[] = $result->summary;
        }
        if ($result->meta !== []) {
            $texts[] = Json::encode(['_meta' => $result->meta]);
        }

        return $texts === [] ? [Json::encode(null)] : $texts;
    }

    private function structuredContent(McpToolResult $result, ProtocolVersion $protocolVersion): mixed
    {
        // `structuredContent` exists since 2025-06-18; older clients read the text block only.
        if (!$protocolVersion->isAtLeast(ProtocolVersion::V2025_06_18)) {
            return null;
        }

        if ($result->error !== null) {
            return ['error' => array_filter([
                'code' => $result->error->code,
                'message' => $result->error->message,
                'details' => $result->error->details,
            ], static fn (mixed $value): bool => $value !== [])];
        }

        if ($result->data === null) {
            return null;
        }

        // Before 2026-07-28 `structuredContent` has to be a JSON object; a list or a scalar is wrapped.
        if ($protocolVersion->requiresObjectStructuredContent() && !$this->isJsonObject($result->data)) {
            return ['result' => $result->data];
        }

        return $result->data;
    }

    /**
     * @return array<string, mixed>
     */
    private function meta(McpToolResult $result, bool $specOnly): array
    {
        $meta = [];
        if ($specOnly) {
            // In the legacy text the metadata is already inside the envelope.
            foreach ($result->meta as $key => $value) {
                $meta[self::META_PREFIX . $key] = $value;
            }
        }

        $meta[self::META_PREFIX . 'generatedAt'] = ($result->generatedAt ?? $this->clock->now())->format(\DateTimeInterface::ATOM);
        if ($result->expiresAt !== null) {
            $meta[self::META_PREFIX . 'expiresAt'] = $result->expiresAt->format(\DateTimeInterface::ATOM);
        }

        return $meta;
    }

    private function isJsonObject(mixed $data): bool
    {
        return $data instanceof \stdClass || (\is_array($data) && $data !== [] && !array_is_list($data));
    }
}
