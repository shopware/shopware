# Gap: Per-user MCP allowlist

Tracked in [product-epic-backlog.md](product-epic-backlog.md) — Workstream 1, row "Per-user MCP allowlist".

## The gap

`api/_mcp` accepts these auth flows today:

| Auth mode | Effective allowlist |
|-----------|---------------------|
| `sw-access-key` + `sw-secret-access-key` (**integration** origin) | `integration.mcp_allowlist` ✓ |
| `sw-access-key` + `sw-secret-access-key` (**user** origin) | rejected by `McpAuthenticationListener` with `unsupportedKeyType()` |
| `Authorization: Bearer <JWT>` (user password grant) | `unrestricted()` — **all tools accessible** ✗ |
| `Authorization: Bearer <JWT>` (integration client credentials) | `integration.mcp_allowlist` ✓ |
| Integration creds + `sw-app-user-id` header (Copilot / merchant-assistant-service) | only the integration's allowlist — no per-user tool control ✗ |

Two real problems:

1. **Users have no per-principal allowlist.** Bearer JWT path bypasses the allowlist entirely. User access keys would resolve to a user but are rejected outright.
2. **Copilot's delegated user identity is invisible to the allowlist.** ACL is intersected, but the MCP allowlist isn't.

## Design

Two parts, applied together:

### A. Shrink the auth surface

Drop bearer JWTs (both grant types) for `/api/_mcp`. Require `sw-access-key` + `sw-secret-access-key`, and accept **both** integration and user origins.

Why:
- JWT bearer flow needs OAuth password grant + 10-minute expiry + refresh token dance. Awkward for long-running MCP clients.
- User access keys are stable, scoped, revocable creds the user can manage from their own profile page (the existing "Integrations / Create access key" panel — see screenshot).
- One auth mechanism is easier to reason about than two.

The Copilot integration + `sw-app-user-id` flow keeps working — it already uses integration access keys, no bearer token involved.

### B. Add per-user allowlist storage + lookup

Allowlist still has to live on the principal (user or integration). The lookup *key* is the access key; the lookup *target* is `user.mcp_allowlist` or `integration.mcp_allowlist` depending on the access key origin.

| Auth mode | Effective allowlist |
|-----------|---------------------|
| Integration access key | `integration.mcp_allowlist` (unchanged) |
| User access key | `user.mcp_allowlist` (new) |
| Integration access key + `sw-app-user-id` | `intersect(integration.mcp_allowlist, user.mcp_allowlist)` |

`NULL` means unrestricted for that principal. `intersect(NULL, [A, B]) = [A, B]`.

## Backend changes

### 1. Migration

Add `mcp_allowlist JSON NULL` to the `user` table.

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

Add after the `admin` field (same shape as `IntegrationDefinition`):

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

### 4. `McpAuthenticationListener.php` — accept user access keys, refuse bearer

**File:** `src/Core/Framework/Mcp/Authentication/McpAuthenticationListener.php`

Currently rejects user-origin keys and falls through to bearer auth when no headers are present. New behaviour:

- Allow `getOrigin($accessKey)` to be either `'integration'` or `'user'`.
- If neither `sw-access-key`/`sw-secret-access-key` is present, throw `McpException::missingCredentials()` (new) instead of falling through. This effectively disables the bearer JWT path.

```php
public function authenticate(ControllerEvent $event): void
{
    $request = $event->getRequest();

    if ($request->attributes->get('_route') !== self::MCP_ROUTE_NAME) {
        return;
    }

    $accessKey = $request->headers->get(PlatformRequest::HEADER_ACCESS_KEY);
    $secretKey = $request->headers->get(self::HEADER_SECRET_ACCESS_KEY);

    if ($accessKey === null || $secretKey === null) {
        throw McpException::missingCredentials();
    }

    $origin = AccessKeyHelper::getOrigin($accessKey);
    if ($origin !== 'integration' && $origin !== 'user') {
        throw McpException::unsupportedKeyType();
    }

    $this->rateLimiter->ensureAccepted(RateLimiter::OAUTH, $accessKey);

    if (!$this->clientRepository->validateClient($accessKey, $secretKey, 'client_credentials')) {
        throw McpException::invalidCredentials();
    }

    $this->rateLimiter->reset(RateLimiter::OAUTH, $accessKey);

    $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_ACCESS_TOKEN_ID, 'mcp-' . $accessKey);
    $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID, $accessKey);
    $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_PRE_AUTHENTICATED, true);
}
```

`ApiRequestContextResolver` already handles user-origin client IDs (resolves `user_access_key.user_id` and builds `AdminApiSource` with the user's permissions). No change needed there.

### 5. `McpAllowlistProvider.php` — origin-aware lookup

**File:** `src/Core/Framework/Mcp/AllowList/McpAllowlistProvider.php`

Add `use Shopware\Core\Framework\Api\Util\AccessKeyHelper;` and `use Shopware\Core\Framework\Uuid\Uuid;` imports.

Replace `forCurrentRequest()`:

```php
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

    $origin = AccessKeyHelper::getOrigin($accessKey);

    // User access key: allowlist lives on the user.
    if ($origin === 'user') {
        return $this->forUserAccessKey($accessKey);
    }

    // Integration access key (+ optional delegated user via sw-app-user-id)
    $appUserId = $request->headers->get(PlatformRequest::HEADER_APP_USER_ID);
    if ($appUserId !== null && Uuid::isValid($appUserId)) {
        return $this->intersect(
            $this->forAccessKey($accessKey),
            $this->forUserId($appUserId),
        );
    }

    return $this->forAccessKey($accessKey);
}
```

Add `forUserAccessKey()`:

```php
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

### 6. `UserMcpAllowlistController.php`

**Create:** `src/Core/Framework/Mcp/Controller/UserMcpAllowlistController.php`

Mirrors `IntegrationMcpAllowlistController`. Route: `POST /api/_action/user/{userId}/mcp-allowlist`. ACL: `users_and_permissions.editor`.

### 7. `McpServerController.php` — generic error messages

**File:** `src/Core/Framework/Mcp/Controller/McpServerController.php`

Drop "for this integration / Settings > Integrations" wording from rejection messages. Use generic copy:

```
'Tool "%s" is not enabled in your MCP allowlist.'
'Resource "%s" is not enabled in your MCP allowlist.'
'Prompt "%s" is not enabled in your MCP allowlist.'
```

### 8. `McpException.php`

Add `missingCredentials()` for the case where no `sw-access-key`/`sw-secret-access-key` headers are present.

## Admin UI changes

### 9. User detail page

**File:** `src/Administration/Resources/app/administration/src/module/sw-users-permissions/page/sw-users-permissions-user-detail/sw-users-permissions-user-detail.html.twig`

The user detail page already has an **Integrations** card with a "Create access key" panel (see merchant-facing screenshot). Add an MCP tools card alongside it, reusing the existing `sw-integration-mcp-allowlist` component:

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

The "Integrations" card already on the user detail page is what merchants will use to mint MCP credentials — no separate UI for that.

## Identity exposure (clients learning who they are)

An MCP client speaks MCP, not Shopware's admin REST API. So everything it needs — who it is, which session it's on — must come through the MCP channel. Two pieces, both already half-built:

- **`Mcp-Session-Id`** response header on every reply (already done by `vendor/mcp/sdk/src/Server/Transport/StreamableHttpTransport.php:33`).
- **`_meta.shopware`** on the `initialize` result (new).

Together: the client stores the initialize response, learns its identity once, and keys subsequent state by `Mcp-Session-Id`. No extra round-trip, no REST awareness required.

### `_meta.shopware` on `initialize` response

The `_meta` field is the MCP-blessed escape hatch for server-specific data. Use it on the `initialize` result:

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "result": {
    "protocolVersion": "2025-06-18",
    "capabilities": {...},
    "serverInfo": {"name": "Shopware MCP", "version": "6.8.0"},
    "_meta": {
      "shopware": {
        "user":        { "id": "abc...", "username": "testing", "admin": false },
        "integration": { "id": "def..." }
      }
    }
  }
}
```

Populated from the resolved `AdminApiSource`:

| Auth mode | `user` block | `integration` block |
|---|---|---|
| User access key | populated | `null` |
| Integration access key | `null` | populated |
| Integration + `sw-app-user-id` | populated (impersonated) | populated |

Only on `initialize` — identity doesn't change mid-session, no point repeating it on every response.

**Implementation:** `McpServerController::handle()` already post-processes the response (`filterListResponse()`). Add a sibling `enrichInitializeResponse()` that runs when the request body's `method === 'initialize'` and injects `_meta.shopware` into `result`. Same JSON-rewrite pattern.

### Session attribution (audit trail)

`Mcp-Session-Id` is already set on every response by the transport. Once `_meta.shopware.user.id` is exposed on `initialize`, the audit pattern is:

| Event | Log row |
|---|---|
| `initialize` | `(session_id, user_id, integration_id, access_key, client_ip, ua, started_at)` — once |
| `tools/call`, `resources/read`, … | `(session_id, method, name, params_hash, ts)` — many |

Join on `session_id` for full attribution. No identity repeated per-request. Persisting these rows is **out of scope for this gap** — tracked separately under MCP audit logging.

## "App needs more tools" pattern

No protocol changes needed. The flow works through existing mechanisms:

1. After connecting, the app calls `tools/list` — already filtered by the effective allowlist.
2. The app compares the response against its internal list of required tools per feature.
3. If a required tool is missing, the app shows a targeted message, e.g.:
   > "Feature X requires the 'merchant-order-summary' tool. Ask your admin to enable it under Settings > Users > [Username] > MCP Tools."
4. When a blocked `tools/call` is attempted, the JSON-RPC error reads:
   > "Tool 'X' is not enabled in your MCP allowlist."
   The app can parse this and surface a prompt to the user.

This is a merchant-assistant-service / Copilot UI responsibility — no additional Shopware endpoint is needed.

## Files to create / modify

| Action | File |
|--------|------|
| Create | `src/Core/Migration/V6_7/Migration{ts}AddMcpAllowlistToUser.php` |
| Modify | `src/Core/System/User/UserDefinition.php` |
| Modify | `src/Core/System/User/UserEntity.php` |
| Modify | `src/Core/Framework/Mcp/Authentication/McpAuthenticationListener.php` |
| Modify | `src/Core/Framework/Mcp/AllowList/McpAllowlistProvider.php` |
| Create | `src/Core/Framework/Mcp/Controller/UserMcpAllowlistController.php` |
| Modify | `src/Core/Framework/Mcp/Controller/McpServerController.php` (rejection messages + `_meta.shopware` on `initialize`) |
| Modify | `src/Core/Framework/Mcp/McpException.php` |
| Modify | `src/Administration/.../sw-users-permissions-user-detail.html.twig` + `index.js` |

## Verification

1. `phpunit_run src/Core/Framework/Mcp/` — existing allowlist tests pass.
2. `phpstan_analyze src/Core/System/User/ src/Core/Framework/Mcp/`
3. **Bearer token rejected:** `Authorization: Bearer <user JWT>` against `/api/_mcp` returns the new `missingCredentials()` error.
4. **User access key:** create a `user_access_key`, set `user.mcp_allowlist = '{"tools":["hello"]}'`, call `tools/list` with the user access key — only `hello` visible.
5. **Integration access key (unchanged):** integration allowlist still applied, no regression.
6. **Copilot pattern:** integration access key + `sw-app-user-id` header — effective list = intersection of integration and user allowlists.
