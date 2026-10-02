<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Result;

use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0
 *
 * What a tool call produced, independent of any output format. {@see McpToolResultRenderer} turns it
 * into the MCP result, so tools and extensions never build the wire format themselves. The parts are
 * named by meaning, not by MCP field: no `isError`, `structuredContent` or `_meta` here.
 *
 * See adr/2026-09-24-mcp-tool-result-envelope.md.
 */
#[Package('framework')]
final readonly class McpToolResult
{
    /**
     * @param mixed $data the machine-readable result, any JSON-serializable value
     * @param array<string, mixed> $meta well-known metadata (for example `responseSize`, `dryRun`) and extension values under their own prefix
     * @param \DateTimeImmutable|null $generatedAt when the data was produced; the renderer uses the current time when null
     * @param \DateTimeImmutable|null $expiresAt until when the result is valid, when that is known
     */
    public function __construct(
        public mixed $data = null,
        public ?string $summary = null,
        public ?McpToolError $error = null,
        public array $meta = [],
        public ?\DateTimeImmutable $generatedAt = null,
        public ?\DateTimeImmutable $expiresAt = null,
    ) {
    }

    /**
     * @param array<string, mixed> $meta
     */
    public static function success(mixed $data, array $meta = [], ?string $summary = null): self
    {
        return new self(data: $data, summary: $summary, meta: $meta);
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function failure(string $message, string $code = McpToolError::TOOL_ERROR, array $details = []): self
    {
        return new self(error: new McpToolError($message, $code, $details));
    }

    public function isError(): bool
    {
        return $this->error !== null;
    }
}
