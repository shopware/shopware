# Gap: Per-user MCP allowlist

Tracked in [product-epic-backlog.md](product-epic-backlog.md) — Workstream 1, row "Per-user MCP allowlist".

## The gap

`api/_mcp` supports two auth flows today:

| Auth mode | What happens to allowlist |
|-----------|--------------------------|
| Integration credentials (`sw-access-key` + `sw-secret-access-key`) | `McpAllowlistProvider` reads `integration.mcp_allowlist` — works correctly |
| User bearer token (OAuth2 password-grant JWT) | `ATTRIBUTE_OAUTH_USER_ID` is set; `ATTRIBUTE_OAUTH_CLIENT_ID` = JWT `aud` claim (not an integration key); integration table lookup returns nothing; `unrestricted()` is returned — **all tools accessible** |

A third flow exists via the **merchant-assistant-service (AI Copilot)**:

- Service authenticates as an integration (OAuth2 client credentials)
- Passes the end-user's identity via `sw-app-user-id` header (`PlatformRequest::HEADER_APP_USER_ID`)
- `ApiRequestContextResolver` intersects integration ACL with user ACL
- But `McpAllowlistProvider` only sees the integration's allowlist — no per-user tool control

## Target behaviour

| Auth mode | Effective allowlist |
|-----------|-------------------|
| User bearer token | user's `mcp_allowlist` |
| Integration + `sw-app-user-id` | intersection(integration allowlist, user allowlist) |
| Integration only | integration allowlist (unchanged) |

`NULL` in either allowlist means unrestricted for that principal — the intersection of `NULL` and `[A, B]` is `[A, B]`.

## Backend changes

### 1. Migration

Add `mcp_allowlist JSON NULL` column to the `user` table.

**Create:** `src/Core/Migration/V6_7/Migration{timestamp}AddMcpAllowlistToUser.php`

```php
public function update(Connection $connection): void
{
    $connection->executeStatement('
        ALTER TABLE `user`
        ADD COLUMN `mcp_allowlist` JSON NULL AFTER `admin`
    ');
}
```

### 2. `UserDefinition.php`

**File:** `src/Core/System/User/UserDefinition.php`

Add after the `admin` field (same definition as `IntegrationDefinition`):

```php
(new JsonField('mcp_allowlist', 'mcpAllowlist'))->setDescription(
    'Optional per-type MCP allowlist for this user. Structured as {tools, resources, prompts} '
    . 'where each key is null (unrestricted) or a list of allowed names/URIs.'
),
```

### 3. `UserEntity.php`

**File:** `src/Core/System/User/UserEntity.php`

```php
/** @var array<string, mixed>|null */
protected ?array $mcpAllowlist = null;

public function getMcpAllowlist(): ?array { return $this->mcpAllowlist; }
public function setMcpAllowlist(?array $mcpAllowlist): void { $this->mcpAllowlist = $mcpAllowlist; }
```

### 4. `McpAllowlistProvider.php`

**File:** `src/Core/Framework/Mcp/AllowList/McpAllowlistProvider.php`

Add `use Shopware\Core\Framework\Uuid\Uuid;` import.

Replace `forCurrentRequest()`:

```php
public function forCurrentRequest(): array
{
    $request = $this->requestStack->getMainRequest();
    if ($request === null) {
        return $this->unrestricted();
    }

    $userId    = $request->attributes->getString(PlatformRequest::ATTRIBUTE_OAUTH_USER_ID);
    $accessKey = $request->attributes->getString(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID);
    $appUserId = $request->headers->get(PlatformRequest::HEADER_APP_USER_ID);

    // User bearer token (password-grant JWT)
    if ($userId !== '') {
        return $this->forUserId($userId);
    }

    // Integration auth + delegated user (Copilot / merchant-assistant-service)
    if ($accessKey !== '' && $appUserId !== null && Uuid::isValid($appUserId)) {
        return $this->intersect(
            $this->forAccessKey($accessKey),
            $this->forUserId($appUserId),
        );
    }

    // Integration credentials only (existing behaviour, unchanged)
    if ($accessKey !== '') {
        return $this->forAccessKey($accessKey);
    }

    return $this->unrestricted();
}
```

Add `forUserId()`:

```php
public function forUserId(string $userId): array
{
    $json = $this->connection->fetchOne(
        'SELECT `mcp_allowlist` FROM `user` WHERE `id` = :id AND `active` = 1',
        ['id' => Uuid::fromHexToBytes($userId)],
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

    $tools     = $this->extractStringList($allowlist, self::TOOLS);
    $resources = $this->extractStringList($allowlist, self::RESOURCES);
    $prompts   = $this->extractStringList($allowlist, self::PROMPTS);

    return [
        self::TOOLS     => $tools !== null ? $this->expandWithDependencies($tools) : null,
        self::RESOURCES => $resources,
        self::PROMPTS   => $prompts,
    ];
}
```

Add `intersect()` and `intersectList()`:

```php
/**
 * @param array{tools: list<string>|null, resources: list<string>|null, prompts: list<string>|null} $a
 * @param array{tools: list<string>|null, resources: list<string>|null, prompts: list<string>|null} $b
 * @return array{tools: list<string>|null, resources: list<string>|null, prompts: list<string>|null}
 */
private function intersect(array $a, array $b): array
{
    return [
        self::TOOLS     => $this->intersectList($a[self::TOOLS], $b[self::TOOLS]),
        self::RESOURCES => $this->intersectList($a[self::RESOURCES], $b[self::RESOURCES]),
        self::PROMPTS   => $this->intersectList($a[self::PROMPTS], $b[self::PROMPTS]),
    ];
}

/** @param list<string>|null $a @param list<string>|null $b @return list<string>|null */
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
```

### 5. `UserMcpAllowlistController.php`

**Create:** `src/Core/Framework/Mcp/Controller/UserMcpAllowlistController.php`

Mirrors `IntegrationMcpAllowlistController`. Route: `POST /api/_action/user/{userId}/mcp-allowlist`. ACL: `users_and_permissions.editor`.

### 6. `McpServerController.php` — generic error messages

**File:** `src/Core/Framework/Mcp/Controller/McpServerController.php`

Drop "for this integration / Settings > Integrations" wording from tool/resource/prompt rejection messages. Use generic copy:

```
'Tool "%s" is not enabled in your MCP allowlist.'
'Resource "%s" is not enabled in your MCP allowlist.'
'Prompt "%s" is not enabled in your MCP allowlist.'
```

## Admin UI changes

### 7. User detail page

**File:** `src/Administration/Resources/app/administration/src/module/sw-users-permissions/page/sw-users-permissions-user-detail/sw-users-permissions-user-detail.html.twig`

Add an MCP tools card below the ACL roles section, reusing the existing `sw-integration-mcp-allowlist` component:

```twig
<mt-card :title="$tc('sw-users-permissions.users.user-detail.mcpAllowlistTitle')">
    <sw-integration-mcp-allowlist
        :allowlist="user.mcpAllowlist"
        :disabled="!acl.can('users_and_permissions.editor')"
        :is-admin="user.admin"
        :granted-privileges="user.aclRoles"
        @update:allowlist="onMcpAllowlistUpdate"
    />
</mt-card>
```

Wire `onMcpAllowlistUpdate` to `POST /api/_action/user/{userId}/mcp-allowlist`.

## "App needs more tools" pattern

No protocol changes needed. The flow works through existing mechanisms:

1. After connecting, the app calls `tools/list` — already filtered by the effective allowlist.
2. The app compares the response against its internal list of required tools per feature.
3. If a required tool is missing, the app shows a targeted message, e.g.:
   > "Feature X requires the 'merchant-order-summary' tool. Ask your admin to enable it under Settings > Users > [Username] > MCP Tools."
4. When a blocked `tools/call` is attempted, the JSON-RPC error (from step 6 above) reads:
   > "Tool 'X' is not enabled in your MCP allowlist."
   The app can parse this and surface a prompt to the user.

This is a merchant-assistant-service / Copilot UI responsibility — no additional Shopware endpoint is needed.

## Files to create / modify

| Action | File |
|--------|------|
| Create | `src/Core/Migration/V6_7/Migration{ts}AddMcpAllowlistToUser.php` |
| Modify | `src/Core/System/User/UserDefinition.php` |
| Modify | `src/Core/System/User/UserEntity.php` |
| Modify | `src/Core/Framework/Mcp/AllowList/McpAllowlistProvider.php` |
| Create | `src/Core/Framework/Mcp/Controller/UserMcpAllowlistController.php` |
| Modify | `src/Core/Framework/Mcp/Controller/McpServerController.php` |
| Modify | `src/Administration/.../sw-users-permissions-user-detail.html.twig` + `index.js` |

## Verification

1. `phpunit_run src/Core/Framework/Mcp/` — existing allowlist tests pass.
2. `phpstan_analyze src/Core/System/User/ src/Core/Framework/Mcp/`
3. User bearer token: set `user.mcp_allowlist = '{"tools":["hello"]}'`, call `tools/list` with user JWT — only `hello` visible.
4. Copilot pattern: call as integration + `sw-app-user-id` header — effective list = intersection of integration and user allowlists.
5. Integration-only: no `sw-app-user-id` header — integration allowlist unchanged.
