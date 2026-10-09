<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\AllowList;

use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0
 *
 * Typed representation of the per-principal MCP capability allowlist.
 * null for a type means unrestricted (all capabilities allowed).
 * An empty array means the type is fully blocked.
 *
 * @internal
 */
#[Package('framework')]
final class McpAllowlist
{
    public const TOOLS = 'tools';
    public const RESOURCES = 'resources';
    public const PROMPTS = 'prompts';

    /**
     * @param list<string>|null $tools null = all tools allowed
     * @param list<string>|null $resources null = all resources allowed
     * @param list<string>|null $prompts null = all prompts allowed
     */
    public function __construct(
        public readonly ?array $tools,
        public readonly ?array $resources,
        public readonly ?array $prompts,
    ) {
    }

    public static function unrestricted(): self
    {
        return new self(null, null, null);
    }

    public static function blocked(): self
    {
        return new self([], [], []);
    }

    /**
     * Parses a stored allowlist for a principal without the administrator bypass: anything not
     * explicitly selected is blocked, so every unusable value resolves to an empty list rather than
     * to null. See the allowlist section of Mcp/AGENTS.md.
     */
    public static function restrictedFromJson(?string $json): self
    {
        if ($json === null || $json === '') {
            return self::blocked();
        }

        try {
            $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return self::blocked();
        }

        if (!\is_array($data)) {
            return self::blocked();
        }

        return new self(
            tools: self::extractList($data, self::TOOLS) ?? [],
            resources: self::extractList($data, self::RESOURCES) ?? [],
            prompts: self::extractList($data, self::PROMPTS) ?? [],
        );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<string>|null
     */
    private static function extractList(array $data, string $key): ?array
    {
        if (!\array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }

        // A JSON object decodes to an associative array. It is not a list of capability names, so
        // it must not be read as one: under restrictedFromJson() the caller gets an empty selection.
        if (!\is_array($data[$key]) || !array_is_list($data[$key])) {
            return null;
        }

        return array_values(array_filter($data[$key], 'is_string'));
    }
}
