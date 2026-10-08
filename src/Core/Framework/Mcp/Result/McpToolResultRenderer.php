<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Result;

use Mcp\Schema\Content\ResourceLink;
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
     * `{"_meta": …}`. A summary never replaces the data. A failure sends its message, then its details.
     *
     * @return non-empty-list<string>
     */
    private function specTexts(McpToolResult $result): array
    {
        if ($result->error !== null) {
            // The details, for example the violations of a rejected call, are what the model needs to fix it.
            return $result->error->details === []
                ? [$result->error->message]
                : [$result->error->message, Json::encode(['details' => $result->error->details])];
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
