<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Result;

use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 *
 * Reads the legacy `{"success": …}` string that tools, plugins and apps return today into a
 * {@see McpToolResult}, so it can be rendered like any other result. A string that is not that
 * envelope is left alone and returned by the SDK as plain text, as before.
 */
#[Package('framework')]
class McpToolResultParser
{
    private const ENVELOPE_KEYS = ['success' => true, 'data' => true, '_meta' => true, 'error' => true, 'code' => true];

    public function parse(string $text): ?McpToolResult
    {
        // Objects, not associative arrays: `json_decode(…, true)` would turn an empty JSON object in the
        // data into `[]`.
        $decoded = json_decode($text, false);
        if (!$decoded instanceof \stdClass || !\is_bool($decoded->success ?? null)) {
            return null;
        }

        $meta = isset($decoded->_meta) && $decoded->_meta instanceof \stdClass ? get_object_vars($decoded->_meta) : [];

        // Keys next to the envelope fields, such as `dryRun` and `preview` of agentic-commerce's UCP tools,
        // tell a preview from a committed change. They are kept as metadata, so they reach the client.
        foreach (get_object_vars($decoded) as $key => $value) {
            if (!isset(self::ENVELOPE_KEYS[$key])) {
                $meta[$key] ??= $value;
            }
        }

        if ($decoded->success) {
            return $this->success($decoded->data ?? null, $meta);
        }

        return new McpToolResult(error: $this->error($decoded), meta: $meta);
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function success(mixed $data, array $meta): McpToolResult
    {
        // A result too large to return inline: McpToolResponse stored it and put the pointer in `_meta`.
        // Only that pointer becomes a link; another `resourceUri` of an extension stays plain metadata.
        $uri = $meta['resourceUri'] ?? null;
        if (!\is_string($uri) || !str_starts_with($uri, McpToolResultPointer::URI_PREFIX)) {
            return new McpToolResult(data: $data, meta: $meta);
        }

        $note = \is_string($meta['note'] ?? null) ? $meta['note'] : null;
        $expiresAt = \is_string($meta['expiresAt'] ?? null) ? \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $meta['expiresAt']) : false;

        return new McpToolResult(
            data: $data,
            summary: $note,
            meta: $meta,
            expiresAt: $expiresAt ?: null,
            links: [new McpToolResultLink(
                $uri,
                'tool-result',
                $note,
                'application/json',
                \is_int($meta['responseSize'] ?? null) ? $meta['responseSize'] : null,
            )],
        );
    }

    private function error(\stdClass $envelope): McpToolError
    {
        $error = $envelope->error ?? null;
        $code = \is_string($envelope->code ?? null) ? $envelope->code : McpToolError::TOOL_ERROR;

        if (\is_string($error)) {
            return new McpToolError($error, $code);
        }

        // Some tools (for example agentic-commerce's UCP tools) return a structured error object.
        if ($error instanceof \stdClass) {
            $details = get_object_vars($error);
            $message = \is_string($details['message'] ?? null) ? $details['message'] : 'The tool call failed.';
            $code = \is_string($details['code'] ?? null) ? $details['code'] : $code;
            unset($details['message'], $details['code']);

            return new McpToolError($message, $code, $details);
        }

        return new McpToolError('The tool call failed.', $code);
    }
}
