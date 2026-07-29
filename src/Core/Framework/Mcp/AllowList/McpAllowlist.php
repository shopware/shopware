<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\AllowList;

use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
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

    public static function fromJson(?string $json): self
    {
        if ($json === null || $json === '') {
            return self::unrestricted();
        }

        try {
            $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return self::unrestricted();
        }

        if (!\is_array($data)) {
            return self::unrestricted();
        }

        return new self(
            tools: self::extractList($data, self::TOOLS),
            resources: self::extractList($data, self::RESOURCES),
            prompts: self::extractList($data, self::PROMPTS),
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

        if (!\is_array($data[$key])) {
            return null;
        }

        return array_values(array_filter($data[$key], 'is_string'));
    }
}
