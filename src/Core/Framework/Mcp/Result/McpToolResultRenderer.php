<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Result;

use Mcp\Schema\Content\ResourceLink;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Result\CallToolResult;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
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
 * `CallToolResult` in the format of the negotiated protocol revision, see {@see McpToolResultCapabilities}:
 *
 * - Since 2025-06-18: `structuredContent` for the data, `isError` for failures, `resource_link` for stored
 *   results, and text blocks for models, which many clients pass on instead of `structuredContent`: the
 *   plain data (or the error message), then the summary and the metadata as blocks of their own. Other
 *   clients pass only `structuredContent` to the model, so it carries the metadata a model needs as well,
 *   under {@see self::STRUCTURED_META_KEY}.
 * - Before 2025-06-18: the deprecated legacy `{"success": …}` envelope as the only text block, which is
 *   all these revisions can carry.
 *
 * It also decides about size: a successful result whose rendered form exceeds the limit of the
 * negotiated revision is stored for the calling principal and sent as a link, whichever tool or app
 * produced it.
 */
#[Package('framework')]
class McpToolResultRenderer
{
    /**
     * The largest result sent inline since 2025-06-18, counted as the text blocks plus the encoded
     * `structuredContent`. The data is sent twice, so this is about 100 KB of data, the limit that applied to
     * the tool's string before the renderer measured the rendered result.
     */
    public const MAX_INLINE_SIZE = 200_000;

    /**
     * Revisions before 2025-06-18 have no `structuredContent` and get the data once, in the text block.
     */
    public const MAX_INLINE_SIZE_TEXT_ONLY = 100_000;

    /**
     * The tool's own metadata sent with the link to a stored result. Values beyond it are left out and
     * named in `omittedMeta`, so a link never carries the bulk that made the result too large.
     */
    public const MAX_LINKED_META_SIZE = 10_000;

    /**
     * The tool's own summary sent with the link to a stored result; a longer one is cut.
     */
    public const MAX_LINKED_SUMMARY_SIZE = 2_000;

    /**
     * The reserved key in `structuredContent` that carries the metadata and the summary for the model, next
     * to the keys of the data, or next to `result` when the data isn't a JSON object.
     */
    public const STRUCTURED_META_KEY = '_meta';

    /**
     * Metadata that is bookkeeping for the client, not information for the model.
     */
    private const INTERNAL_META_KEYS = ['responseSize', 'resourceUri'];

    private const META_PREFIX = 'shopware/';

    public function __construct(
        private readonly ClockInterface $clock,
        private readonly ToolResultCacheStorage $storage,
        private readonly RequestStack $requestStack,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param string|null $legacyText the string the tool returned, if it returned the legacy envelope; sent
     *                                as is to clients before 2025-06-18, so they see exactly what they saw before
     * @param CallToolRequest|null $call the call that produced the result; a stored result echoes its tool
     *                                   name and arguments, so the model can tell which call a link belongs to
     * @param bool $hasOutputSchema the tool declares an `outputSchema`; its `structuredContent` is then exactly
     *                              the data the schema describes, without the metadata for the model
     */
    public function render(McpToolResult $result, ProtocolVersion $protocolVersion, ?string $legacyText = null, ?CallToolRequest $call = null, bool $hasOutputSchema = false): CallToolResult
    {
        $capabilities = McpToolResultCapabilities::forRevision($protocolVersion);
        $rendered = $this->renderInline($result, $capabilities, $legacyText, $hasOutputSchema);
        if ($result->isError()) {
            return $rendered;
        }

        $size = $this->renderedSize($rendered);
        if ($size <= $capabilities->inlineLimit) {
            return $rendered;
        }

        $request = $this->requestStack->getCurrentRequest();
        $principal = $request !== null ? McpToolResultPrincipal::fromRequest($request) : null;
        if ($request === null || $principal === null) {
            // Nobody could read a stored result back (CLI, tests, a Store API call without `sw-context-token`).
            return $rendered;
        }

        $format = $capabilities->storedFormat;
        $content = $format === McpToolResultStoredFormat::Spec ? Json::encode($result->data) : ($legacyText ?? $this->legacyEnvelope($result));
        // The pointer is signed for the principal, so it works without an MCP session. The session id is
        // only recorded for the cleanup when a handshake session ends.
        $pointer = $this->storage->storeFor($principal, $content, $request->headers->get('Mcp-Session-Id') ?? '', $format->value);

        $this->logger->debug('MCP tool result stored as resource (oversized)', [
            'tool' => $call?->name,
            'renderedSize' => $size,
            'resourceUri' => $pointer->uri(),
        ]);

        [$linked, $linkedLegacyText] = $this->linkedResult($result, $pointer, \strlen($content), $capabilities, $call);

        return $this->renderInline($linked, $capabilities, $linkedLegacyText, $hasOutputSchema);
    }

    private function renderInline(McpToolResult $result, McpToolResultCapabilities $capabilities, ?string $legacyText, bool $hasOutputSchema): CallToolResult
    {
        if (!$capabilities->specFormat) {
            return new CallToolResult(
                [new TextContent($legacyText ?? $this->legacyEnvelope($result))],
                isError: $result->isError(),
                meta: $this->timestamps($result),
            );
        }

        $meta = [];
        foreach ($result->meta as $key => $value) {
            $meta[self::META_PREFIX . $key] = $value;
        }

        return new CallToolResult(
            [
                ...array_map(static fn (string $text): TextContent => new TextContent($text), $this->specTexts($result)),
                ...$this->links($result),
            ],
            isError: $result->isError(),
            structuredContent: $this->structuredContent($result, $capabilities, $hasOutputSchema),
            meta: [...$meta, ...$this->timestamps($result)],
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
     * The result that replaces a stored one, and its legacy text. The tool's summary and links are kept, and
     * its metadata as far as it fits {@see self::MAX_LINKED_META_SIZE}. The legacy text is what
     * `McpToolResponse::success()` returned for a stored result before the renderer took over, so clients
     * before 2025-06-18 keep finding the pointer in `_meta.resourceUri`.
     *
     * @return array{McpToolResult, string}
     */
    private function linkedResult(McpToolResult $result, McpToolResultPointer $pointer, int $storedSize, McpToolResultCapabilities $capabilities, ?CallToolRequest $call): array
    {
        $note = 'Response too large for inline delivery. '
            . 'Prefer re-running the tool with tighter "includes" or a lower "limit" to get a smaller inline result. '
            . ($capabilities->specFormat
                ? 'If you need the full data as-is, fetch it via resources/read using the URI of the resource_link.'
                : 'If you need the full data as-is, fetch it via resources/read using the URI in _meta.resourceUri.');

        $meta = $this->boundedMeta($result->meta);
        $meta['responseSize'] = $storedSize;
        if ($call !== null && $call->name !== '') {
            $arguments = $call->arguments;
            $meta['query'] = \strlen(Json::encode($arguments)) <= self::MAX_LINKED_META_SIZE
                ? ['tool' => $call->name, 'arguments' => $arguments]
                : ['tool' => $call->name];
        }

        $legacyMeta = [
            ...$meta,
            'resourceUri' => $pointer->uri(),
            'expiresAt' => $pointer->expiresAt->format(\DateTimeInterface::ATOM),
            'note' => $note,
        ];

        $summary = $result->summary;
        if ($summary !== null && \strlen($summary) > self::MAX_LINKED_SUMMARY_SIZE) {
            $summary = mb_strcut($summary, 0, self::MAX_LINKED_SUMMARY_SIZE) . '…';
        }

        $linked = new McpToolResult(
            summary: $summary !== null && $summary !== '' ? $summary . "\n\n" . $note : $note,
            // Since 2025-06-18 the `resource_link` carries the pointer; older revisions only read the text.
            meta: $capabilities->specFormat ? $meta : [...$meta, 'resourceUri' => $pointer->uri()],
            generatedAt: $result->generatedAt,
            expiresAt: $pointer->expiresAt,
            links: [...$result->links, new McpToolResultLink($pointer->uri(), 'tool-result', $note, 'application/json', $storedSize)],
        );

        return [$linked, Json::encode(['success' => true, 'data' => null, '_meta' => $legacyMeta])];
    }

    /**
     * The tool's metadata in its order, as long as it fits {@see self::MAX_LINKED_META_SIZE}. A value that
     * doesn't fit, such as a large `preview` of an app tool, is named in `omittedMeta`. Clients before
     * 2025-06-18 find it in the stored envelope; since 2025-06-18 the stored resource is the plain data.
     *
     * @param array<string, mixed> $meta
     *
     * @return array<string, mixed>
     */
    private function boundedMeta(array $meta): array
    {
        $bounded = [];
        $omitted = [];
        $size = 0;
        foreach ($meta as $key => $value) {
            $entrySize = \strlen((string) $key) + \strlen(Json::encode($value));
            if ($size + $entrySize > self::MAX_LINKED_META_SIZE) {
                $omitted[] = (string) $key;

                continue;
            }
            $size += $entrySize;
            $bounded[$key] = $value;
        }

        if ($omitted !== []) {
            $bounded['omittedMeta'] = $omitted;
        }

        return $bounded;
    }

    /**
     * The legacy envelope that `McpToolResponse::success()` and `::error()` produce. Deprecated, and kept
     * for clients before 2025-06-18.
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
    private function links(McpToolResult $result): array
    {
        return array_map(static fn (McpToolResultLink $link): ResourceLink => new ResourceLink(
            $link->uri,
            $link->name,
            description: $link->description,
            mimeType: $link->mimeType,
            size: $link->size,
        ), $result->links);
    }

    /**
     * The text blocks of the MCP format: the data as plain JSON first, then the summary, then
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

    /**
     * The data, wrapped as `{"result": …}` where the revision needs an object. With metadata or a summary for
     * the model, it is always an object that adds them under {@see self::STRUCTURED_META_KEY}, see
     * {@see self::modelMeta()}.
     */
    private function structuredContent(McpToolResult $result, McpToolResultCapabilities $capabilities, bool $hasOutputSchema): mixed
    {
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

        $modelMeta = $hasOutputSchema ? [] : $this->modelMeta($result);
        $isObject = $this->isJsonObject($result->data);

        if ($modelMeta !== [] && !($isObject && $this->hasKey($result->data, self::STRUCTURED_META_KEY))) {
            // A union keeps numeric keys of the data, which a spread would renumber.
            return $isObject
                ? (array) $result->data + [self::STRUCTURED_META_KEY => $modelMeta]
                : ['result' => $result->data, self::STRUCTURED_META_KEY => $modelMeta];
        }

        if ($capabilities->objectOnlyStructuredContent && !$isObject) {
            return ['result' => $result->data];
        }

        return $result->data;
    }

    /**
     * What the model has to know about the data and can't tell from it, such as `dryRun`, `total` and the
     * paging, or the `usage` hint of tool search: the tool's metadata without the client's bookkeeping,
     * and the summary.
     *
     * @return array<string, mixed>
     */
    private function modelMeta(McpToolResult $result): array
    {
        $meta = array_diff_key($result->meta, array_flip(self::INTERNAL_META_KEYS));
        if ($result->summary !== null && $result->summary !== '' && !\array_key_exists('summary', $meta)) {
            $meta['summary'] = $result->summary;
        }

        return $meta;
    }

    private function hasKey(mixed $data, string $key): bool
    {
        return \is_array($data) ? \array_key_exists($key, $data) : ($data instanceof \stdClass && property_exists($data, $key));
    }

    /**
     * @return array<string, mixed>
     */
    private function timestamps(McpToolResult $result): array
    {
        $meta = [self::META_PREFIX . 'generatedAt' => ($result->generatedAt ?? $this->clock->now())->format(\DateTimeInterface::ATOM)];
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
