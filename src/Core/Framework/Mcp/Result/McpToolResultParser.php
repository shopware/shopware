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
    public function parse(string $text): ?McpToolResult
    {
        // Objects, not associative arrays: `json_decode(…, true)` would turn an empty JSON object in the
        // data into `[]`.
        $decoded = json_decode($text, false);
        if (!$decoded instanceof \stdClass || !\is_bool($decoded->success ?? null)) {
            return null;
        }

        $meta = isset($decoded->_meta) && $decoded->_meta instanceof \stdClass ? $this->toArray($decoded->_meta) : [];

        if ($decoded->success) {
            return new McpToolResult(data: $decoded->data ?? null, meta: $meta);
        }

        return new McpToolResult(error: $this->error($decoded), meta: $meta);
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
            $details = $this->toArray($error);
            $message = \is_string($details['message'] ?? null) ? $details['message'] : 'The tool call failed.';
            $code = \is_string($details['code'] ?? null) ? $details['code'] : $code;
            unset($details['message'], $details['code']);

            return new McpToolError($message, $code, $details);
        }

        return new McpToolError('The tool call failed.', $code);
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(\stdClass $object): array
    {
        $array = [];
        foreach (get_object_vars($object) as $key => $value) {
            $array[(string) $key] = $value;
        }

        return $array;
    }
}
