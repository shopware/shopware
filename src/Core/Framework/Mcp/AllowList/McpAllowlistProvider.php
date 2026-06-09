<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\AllowList;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * Reads the per-principal MCP allowlist from the database for the current request.
 * Returns null for a type when no restriction is configured (all capabilities accessible).
 *
 * Auth mode → allowlist resolution:
 * - User access key (SWUA...) → user.mcp_allowlist via user_id lookup
 * - Integration access key (SWIA...) → integration.mcp_allowlist
 * - Integration + sw-app-user-id (Copilot) → intersect(integration, user)
 * - Bearer JWT, password grant → user.mcp_allowlist via ATTRIBUTE_OAUTH_USER_ID
 * - Bearer JWT, client_credentials → integration.mcp_allowlist via ATTRIBUTE_OAUTH_CLIENT_ID
 * - Admin users (admin=true) → always unrestricted regardless of auth mode
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

        $clientId = $request->attributes->getString(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID);

        if ($clientId !== '') {
            try {
                $origin = AccessKeyHelper::getOrigin($clientId);
            } catch (\Throwable) {
                // Unknown prefix (e.g. 'administration' for password-grant JWTs) — fall through to bearer JWT path.
                $origin = '';
            }

            if ($origin === 'user') {
                return $this->forUserAccessKey($clientId);
            }

            if ($origin === 'integration') {
                $appUserId = $request->headers->get(PlatformRequest::HEADER_APP_USER_ID);
                if ($appUserId !== null && Uuid::isValid($appUserId)) {
                    return $this->intersect(
                        $this->forAccessKey($clientId),
                        $this->forUserId($appUserId),
                    );
                }

                return $this->forAccessKey($clientId);
            }
        }

        // Bearer JWT (password grant): ATTRIBUTE_OAUTH_CLIENT_ID = 'administration'
        $userId = $request->attributes->getString(PlatformRequest::ATTRIBUTE_OAUTH_USER_ID);
        if ($userId !== '' && Uuid::isValid($userId)) {
            return $this->forUserId($userId);
        }

        return $this->unrestricted();
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
     * @return array{tools: list<string>|null, resources: list<string>|null, prompts: list<string>|null}
     */
    public function forUserId(string $userId): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT `mcp_allowlist`, `admin` FROM `user` WHERE `id` = :id AND `active` = 1',
            ['id' => Uuid::fromHexToBytes($userId)],
        );

        // Admin users bypass ACL checks — mirror that for MCP allowlist.
        if ($row === false || (bool) $row['admin']) {
            return $this->unrestricted();
        }

        $json = $row['mcp_allowlist'];

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
     * @return array{tools: list<string>|null, resources: list<string>|null, prompts: list<string>|null}
     */
    private function forUserAccessKey(string $accessKey): array
    {
        $userId = $this->connection->fetchOne(
            'SELECT `user_id` FROM `user_access_key` WHERE `access_key` = :key',
            ['key' => $accessKey],
        );

        if (!\is_string($userId) || $userId === '') {
            return $this->unrestricted();
        }

        return $this->forUserId(Uuid::fromBytesToHex($userId));
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

    /**
     * @param array{tools: list<string>|null, resources: list<string>|null, prompts: list<string>|null} $a
     * @param array{tools: list<string>|null, resources: list<string>|null, prompts: list<string>|null} $b
     *
     * @return array{tools: list<string>|null, resources: list<string>|null, prompts: list<string>|null}
     */
    private function intersect(array $a, array $b): array
    {
        return [
            self::TOOLS => $this->intersectList($a[self::TOOLS], $b[self::TOOLS]),
            self::RESOURCES => $this->intersectList($a[self::RESOURCES], $b[self::RESOURCES]),
            self::PROMPTS => $this->intersectList($a[self::PROMPTS], $b[self::PROMPTS]),
        ];
    }

    /**
     * @param list<string>|null $a
     * @param list<string>|null $b
     *
     * @return list<string>|null
     */
    private function intersectList(?array $a, ?array $b): ?array
    {
        if ($a === null && $b === null) {
            return null;
        }
        if ($a === null) {
            return $b;
        }
        if ($b === null) {
            return $a;
        }

        return array_values(array_intersect($a, $b));
    }
}
