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
        return $this->forCurrentRequest()->tools;
    }

    /**
     * @return list<string>|null null = all resources allowed; array = restrict to listed URIs
     */
    public function resourcesForCurrentRequest(): ?array
    {
        return $this->forCurrentRequest()->resources;
    }

    /**
     * @return list<string>|null null = all prompts allowed; array = restrict to listed names
     */
    public function promptsForCurrentRequest(): ?array
    {
        return $this->forCurrentRequest()->prompts;
    }

    public function forCurrentRequest(): McpAllowlist
    {
        $request = $this->requestStack->getMainRequest();

        if ($request === null) {
            return McpAllowlist::unrestricted();
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

        return McpAllowlist::unrestricted();
    }

    public function forAccessKey(string $accessKey): McpAllowlist
    {
        $json = $this->connection->fetchOne(
            'SELECT `mcp_allowlist` FROM `integration` WHERE `access_key` = :key AND `deleted_at` IS NULL',
            ['key' => $accessKey],
        );

        return $this->fromAllowlist(McpAllowlist::fromJson(\is_string($json) ? $json : null));
    }

    public function forUserId(string $userId): McpAllowlist
    {
        $row = $this->connection->fetchAssociative(
            'SELECT `mcp_allowlist`, `admin` FROM `user` WHERE `id` = :id AND `active` = 1',
            ['id' => Uuid::fromHexToBytes($userId)],
        );

        // Admin users bypass ACL checks — mirror that for MCP allowlist.
        if ($row === false || (bool) $row['admin']) {
            return McpAllowlist::unrestricted();
        }

        return $this->fromAllowlist(McpAllowlist::fromJson(\is_string($row['mcp_allowlist']) ? $row['mcp_allowlist'] : null));
    }

    private function forUserAccessKey(string $accessKey): McpAllowlist
    {
        $userId = $this->connection->fetchOne(
            'SELECT `user_id` FROM `user_access_key` WHERE `access_key` = :key',
            ['key' => $accessKey],
        );

        if (!\is_string($userId) || $userId === '') {
            return McpAllowlist::unrestricted();
        }

        return $this->forUserId(Uuid::fromBytesToHex($userId));
    }

    private function fromAllowlist(McpAllowlist $allowlist): McpAllowlist
    {
        return new McpAllowlist(
            tools: $allowlist->tools !== null ? $this->expandWithDependencies($allowlist->tools) : null,
            resources: $allowlist->resources,
            prompts: $allowlist->prompts,
        );
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

    private function intersect(McpAllowlist $a, McpAllowlist $b): McpAllowlist
    {
        return new McpAllowlist(
            tools: $this->intersectList($a->tools, $b->tools),
            resources: $this->intersectList($a->resources, $b->resources),
            prompts: $this->intersectList($a->prompts, $b->prompts),
        );
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
