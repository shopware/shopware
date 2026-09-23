<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp;

use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 *
 * Forces empty JSON Schema `properties` maps in MCP tool definitions to serialize as `{}` rather
 * than `[]`.
 *
 * A parameterless tool — or a nested object parameter with no members — has an empty properties
 * map, and PHP encodes an empty array as `[]`. JSON Schema requires an object there, so strict
 * clients reject the whole payload — OpenAI answers
 * `400 invalid_function_parameters: "[] is not of type 'object'"`. Because `shopware-toolsets-list`
 * is advertised in every session, one malformed tool breaks every request such a client makes, not
 * just calls to that tool.
 *
 * The MCP SDK normalizes this in its `SchemaGenerator` and in `Tool::fromArray()`, but not in the
 * `Tool` constructor or `Tool::jsonSerialize()`
 * (see https://github.com/modelcontextprotocol/php-sdk/issues/405). Any code path that reconstructs
 * a tool's schema from a JSON-decoded associative array therefore loses the object type, because
 * `json_decode('{"properties":{}}', true)` yields `['properties' => []]`. This is the single place
 * Shopware re-establishes the invariant, shared by every surface that emits a tool definition: the
 * HTTP transport (`tools/list`, over JSON and SSE) and the `shopware-tool-search` payload.
 */
#[Package('framework')]
final class McpToolSchemaNormalizer
{
    /**
     * Normalizes every tool in a JSON-RPC `tools/list` result. Returns the message when a schema
     * changed, or null when nothing changed so callers can skip re-encoding.
     *
     * @param array<string, mixed> $message
     *
     * @return array<string, mixed>|null
     */
    public static function normalizeToolListResult(array $message): ?array
    {
        if (!\is_array($message['result'] ?? null) || !\is_array($message['result']['tools'] ?? null)) {
            return null;
        }

        $changed = false;

        foreach ($message['result']['tools'] as $index => $tool) {
            if (!\is_array($tool)) {
                continue;
            }

            $message['result']['tools'][$index] = self::normalizeToolInto($tool, $changed);
        }

        return $changed ? $message : null;
    }

    /**
     * Normalizes a single tool definition's `inputSchema` and `outputSchema` and returns it. Use
     * when the caller re-serializes unconditionally (e.g. the tool-search payload, where the tool
     * definition is embedded as JSON inside the tool-call result content).
     *
     * @param array<string, mixed> $tool
     *
     * @return array<string, mixed>
     */
    public static function normalizeTool(array $tool): array
    {
        $changed = false;

        return self::normalizeToolInto($tool, $changed);
    }

    /**
     * @param array<string, mixed> $tool
     *
     * @return array<string, mixed>
     */
    private static function normalizeToolInto(array $tool, bool &$changed): array
    {
        foreach (['inputSchema', 'outputSchema'] as $schemaKey) {
            if (\is_array($tool[$schemaKey] ?? null)) {
                $tool[$schemaKey] = self::normalizeSchemaNode($tool[$schemaKey], $changed);
            }
        }

        return $tool;
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
}
