<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Result;

use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Schema\Result\CallToolResult;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;

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
 */
#[Package('framework')]
class McpToolResultRenderer
{
    /**
     * The spec asks for the data twice (`structuredContent` and a text copy). Above this size the
     * structured copy is left out, so a large result is not sent twice; the text still carries it.
     */
    public const MAX_STRUCTURED_TEXT_BYTES = 50_000;

    private const META_PREFIX = 'shopware/';

    /**
     * @internal
     */
    public function __construct(private readonly ClockInterface $clock)
    {
    }

    /**
     * @param string|null $legacyText the string the tool returned, if it returned the legacy envelope;
     *                                kept as is so existing readers see exactly what they saw before
     */
    public function render(McpToolResult $result, ProtocolVersion $protocolVersion, ?string $legacyText = null): CallToolResult
    {
        $specOnly = Feature::isActive('v6.8.0.0');
        $texts = $specOnly ? $this->specTexts($result) : [$legacyText ?? $this->legacyEnvelope($result)];

        return new CallToolResult(
            array_map(static fn (string $text): TextContent => new TextContent($text), $texts),
            isError: $result->isError(),
            structuredContent: \strlen($texts[0]) <= self::MAX_STRUCTURED_TEXT_BYTES ? $this->structuredContent($result, $protocolVersion) : null,
            meta: $this->meta($result, $specOnly),
        );
    }

    /**
     * The legacy envelope that `McpToolResponse::success()` and `::error()` produce.
     */
    public function legacyEnvelope(McpToolResult $result): string
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
     * The text blocks of the spec-only format. The data comes first as plain JSON, because it is
     * the copy of `structuredContent` the spec asks for, and clients that only pass `content` to the
     * model would otherwise lose it. A summary is an additional block, never a replacement.
     *
     * The metadata gets a block of its own as well. `_meta` of the result is for the client, and most
     * clients don't show it to the model, but much of it is written for the model: `dryRun` says a
     * change was only previewed, `total` that there are more results, `usage`, `note` and `resourceUri`
     * what to call next. The legacy envelope carried all of it in the text, and this keeps it there.
     *
     * @return non-empty-list<string>
     */
    private function specTexts(McpToolResult $result): array
    {
        if ($result->error !== null) {
            return [$result->error->message];
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
