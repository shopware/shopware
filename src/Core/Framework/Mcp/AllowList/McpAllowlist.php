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
 * Only administrator users ever reach {@see self::unrestricted()}. Every stored allowlist is parsed
 * through {@see self::restrictedFromJson()}, which resolves a missing, null or unusable value to a
 * blocked type rather than an unrestricted one.
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

    /**
     * No capability of any type is allowed. This is the default for every principal that is not a
     * verified administrator user.
     */
    public static function blocked(): self
    {
        return new self([], [], []);
    }

    /**
     * Parses a stored allowlist for a principal that has no unrestricted bypass: anything the
     * caller did not explicitly select is blocked. A null column, an empty column, unparseable JSON,
     * a missing per-type key, an explicit `null` per-type value and a per-type value of the wrong
     * shape all resolve to an empty list, so no path back to unrestricted access remains.
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

        if (!\is_array($data[$key])) {
            return null;
        }

        return array_values(array_filter($data[$key], 'is_string'));
    }
}
