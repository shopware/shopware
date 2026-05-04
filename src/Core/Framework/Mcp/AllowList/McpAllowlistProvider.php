<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\AllowList;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * Reads the per-integration MCP allowlist from the database for the current request.
 * Returns null for a type when no restriction is configured (all capabilities accessible).
 */
#[Package('framework')]
class McpAllowlistProvider
{
    public const TOOLS = 'tools';
    public const RESOURCES = 'resources';
    public const PROMPTS = 'prompts';

    /**
     * @param array<string, list<string>> $toolDependencies tool-name => [dep-name, ...]
     *
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly RequestStack $requestStack,
        private readonly array $toolDependencies = [],
    ) {
    }

    /**
     * @return list<string>|null null = all tools allowed; array = restrict to listed names
     */
    public function toolsForCurrentRequest(): ?array
    {
        return $this->forCurrentRequest()[self::TOOLS];
    }

    /**
     * @return list<string>|null null = all resources allowed; array = restrict to listed URIs
     */
    public function resourcesForCurrentRequest(): ?array
    {
        return $this->forCurrentRequest()[self::RESOURCES];
    }

    /**
     * @return list<string>|null null = all prompts allowed; array = restrict to listed names
     */
    public function promptsForCurrentRequest(): ?array
    {
        return $this->forCurrentRequest()[self::PROMPTS];
    }

    /**
     * @return array{tools: list<string>|null, resources: list<string>|null, prompts: list<string>|null}
     */
    public function forCurrentRequest(): array
    {
        $request = $this->requestStack->getMainRequest();
        if ($request === null) {
            return $this->unrestricted();
        }

        $accessKey = $request->attributes->getString(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID);
        if ($accessKey === '') {
            return $this->unrestricted();
        }

        return $this->forAccessKey($accessKey);
    }

    /**
     * @return array{tools: list<string>|null, resources: list<string>|null, prompts: list<string>|null}
     */
    public function forAccessKey(string $accessKey): array
    {
        $json = $this->connection->fetchOne(
            'SELECT `mcp_allowlist` FROM `integration` WHERE `access_key` = :key AND `deleted_at` IS NULL',
            ['key' => $accessKey],
        );

        if (!\is_string($json) || $json === '') {
            return $this->unrestricted();
        }

        try {
            $allowlist = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->unrestricted();
        }

        if (!\is_array($allowlist)) {
            return $this->unrestricted();
        }

        $tools = $this->extractStringList($allowlist, self::TOOLS);
        $resources = $this->extractStringList($allowlist, self::RESOURCES);
        $prompts = $this->extractStringList($allowlist, self::PROMPTS);

        return [
            self::TOOLS => $tools !== null ? $this->expandWithDependencies($tools) : null,
            self::RESOURCES => $resources,
            self::PROMPTS => $prompts,
        ];
    }

    /**
     * @return array{tools: null, resources: null, prompts: null}
     */
    private function unrestricted(): array
    {
        return [self::TOOLS => null, self::RESOURCES => null, self::PROMPTS => null];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<string>|null null when key is absent or null (unrestricted); list when key is an array
     */
    private function extractStringList(array $data, string $key): ?array
    {
        if (!\array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }

        if (!\is_array($data[$key])) {
            return null;
        }

        return array_values(array_filter($data[$key], 'is_string'));
    }

    /**
     * Expands the tool allowlist with all transitive dependencies so a tool is never
     * blocked when a peer it functionally requires is not explicitly listed.
     *
     * @param list<string> $allowlist
     *
     * @return list<string>
     */
    private function expandWithDependencies(array $allowlist): array
    {
        $expanded = array_flip($allowlist);
        $queue = $allowlist;

        while ($queue !== []) {
            $toolName = array_shift($queue);

            foreach ($this->toolDependencies[$toolName] ?? [] as $dependency) {
                if (!isset($expanded[$dependency])) {
                    $expanded[$dependency] = true;
                    $queue[] = $dependency;
                }
            }
        }

        return array_keys($expanded);
    }
}
