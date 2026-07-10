# Gap: Per-user MCP allowlist

Tracked in [product-epic-backlog.md](product-epic-backlog.md) — Workstream 1, row "Per-user MCP allowlist".

## Status: Implemented

## What was built

### Auth surface (before vs. after)

| Auth mode | Before | After |
|-----------|--------|-------|
| Integration access key + secret | `integration.mcp_allowlist` | unchanged |
| User access key + secret | rejected (`unsupportedKeyType`) | `user.mcp_allowlist` via user_id lookup |
| Bearer JWT, password grant | `unrestricted()` | `user.mcp_allowlist` via `ATTRIBUTE_OAUTH_USER_ID` |
| Bearer JWT, client_credentials | `unrestricted()` | `integration.mcp_allowlist` via `ATTRIBUTE_OAUTH_CLIENT_ID` |
| Integration + `sw-app-user-id` (Copilot) | `integration.mcp_allowlist` only | intersect(`integration.mcp_allowlist`, `user.mcp_allowlist`) |

`NULL` allowlist means unrestricted for that principal. Admin users (`admin = true`) always bypass the allowlist regardless of auth mode.

### Architecture decision: allowlist lives on the user, not the access key

An earlier iteration placed `mcp_allowlist` on `user_access_key` (one allowlist per key). This was changed: the allowlist lives on `user` (one allowlist per user).

Why:

1. **Consistency with the permission model.** ACL roles and all other permissions sit on the user entity. A per-user allowlist is the natural complement.
2. **Copilot intersection works naturally.** When an integration sends `sw-app-user-id`, the platform already has a user identity to look up. Intersecting `integration.mcp_allowlist` with `user.mcp_allowlist` requires nothing more than reading from `user`. Per-key allowlists would have required a separate user lookup anyway.
3. **Bearer JWT works.** Password-grant JWTs carry the user ID in the `sub` claim, which `SymfonyBearerTokenValidator` stores in `ATTRIBUTE_OAUTH_USER_ID`. Routing these sessions through `forUserId()` requires no new infra.
4. **Simpler Admin UI.** One card on the user detail page, not a per-key modal in the access key grid.

Tradeoff: a user cannot scope individual access keys differently. All keys for a user share the same allowlist. This is intentional — the unit of trust is the user, not the key.

## Implementation

### Migration

**File:** `src/Core/Migration/V6_7/Migration1778142666AddMcpAllowlistToUser.php`

Adds `mcp_allowlist JSON NULL` to the `user` table:

```php
public function update(Connection $connection): void
{
    $this->addColumn($connection, 'user', 'mcp_allowlist', 'JSON');
}

public function updateDestructive(Connection $connection): void {}
```

The `user_access_key.mcp_allowlist` column from an earlier experiment was dropped manually (not released).

### DAL

**`UserDefinition.php`** — added `JsonField('mcp_allowlist', 'mcpAllowlist')` after `BoolField('admin', 'admin')`.

**`UserEntity.php`** — added `$mcpAllowlist`, `getMcpAllowlist()`, `setMcpAllowlist()`.

`UserAccessKeyDefinition.php` and `UserAccessKeyEntity.php` are unchanged (the earlier `mcp_allowlist` field was removed).

### `McpAuthenticationListener.php`

Accepts both `'integration'` and `'user'` key origins. When neither `sw-access-key` nor `sw-secret-access-key` header is present, the listener returns without throwing — standard bearer JWT auth handles the request downstream.

### `McpAllowlistProvider.php`

`forCurrentRequest()` branches on all auth modes:

```php
$clientId = $request->attributes->getString(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID);

if ($clientId !== '') {
    $origin = AccessKeyHelper::getOrigin($clientId);

    if ($origin === 'user') {
        return $this->forUserAccessKey($clientId);       // → forUserId()
    }
    if ($origin === 'integration') {
        $appUserId = $request->headers->get(PlatformRequest::HEADER_APP_USER_ID);
        if ($appUserId !== null && Uuid::isValid($appUserId)) {
            return $this->intersect($this->forAccessKey($clientId), $this->forUserId($appUserId));
        }
        return $this->forAccessKey($clientId);
    }
}

// Bearer JWT password grant: ATTRIBUTE_OAUTH_CLIENT_ID = 'administration'
$userId = $request->attributes->getString(PlatformRequest::ATTRIBUTE_OAUTH_USER_ID);
if ($userId !== '' && Uuid::isValid($userId)) {
    return $this->forUserId($userId);
}

return $this->unrestricted();
```

`forUserId()` reads `mcp_allowlist` and `admin` from the `user` table. Admin users always get `unrestricted()`. `forUserAccessKey()` looks up `user_id` from `user_access_key` and delegates to `forUserId()`. `intersect()` takes the element-wise intersection of two allowlists (null = unrestricted, treated as "allow all" when intersecting).

### `UserMcpAllowlistController.php`

**File:** `src/Core/Framework/Mcp/Controller/UserMcpAllowlistController.php`

```
POST /api/_action/user/{userId}/mcp-allowlist
```

ACL: `api_action_user_mcp-allowlist` (mapped to `users_and_permissions.editor`).

Body: `{ "allowlist": { "tools": [...], "resources": [...], "prompts": [...] } | null }`.

Mirrors `IntegrationMcpAllowlistController` exactly, operating on the `user` repository.

The previous `UserAccessKeyMcpAllowlistController` (`POST /api/_action/user-access-key/{id}/mcp-allowlist`) was deleted.

### Admin UI

**MCP allowlist card** on the user detail page (below the Integrations card):

```twig
<mt-card v-if="feature.isActive('MCP_SERVER')" :title="...">
    <sw-integration-mcp-allowlist
        :allowlist="user.mcpAllowlist"
        :disabled="!acl.can('users_and_permissions.editor') || !$route.params.id"
        :is-admin="user.admin"
        :granted-privileges="mcpGrantedPrivileges"
        @update:allowlist="onMcpAllowlistUpdate"
    />
</mt-card>
```

`onMcpAllowlistUpdate(allowlist)` calls `POST /api/_action/user/{userId}/mcp-allowlist` and updates `user.mcpAllowlist` in place.

The per-key "Edit MCP allowlist" context menu item and its modal were removed from the access key grid.

**`user.api.service.js`** — `saveMcpAllowlist(userId, allowlist)` calls `POST /api/_action/user/{id}/mcp-allowlist`.

**ACL entry** in `sw-users-permissions/acl/index.js`: `api_action_user_mcp-allowlist` under `editor`.

## Files created / modified

| Action | File |
|--------|------|
| Create | `src/Core/Migration/V6_7/Migration1778142666AddMcpAllowlistToUser.php` |
| Modify | `src/Core/System/User/UserDefinition.php` |
| Modify | `src/Core/System/User/UserEntity.php` |
| Modify | `src/Core/System/User/Aggregate/UserAccessKey/UserAccessKeyDefinition.php` (removed field) |
| Modify | `src/Core/System/User/Aggregate/UserAccessKey/UserAccessKeyEntity.php` (removed field) |
| Modify | `src/Core/Framework/Mcp/Authentication/McpAuthenticationListener.php` |
| Modify | `src/Core/Framework/Mcp/AllowList/McpAllowlistProvider.php` |
| Delete | `src/Core/Framework/Mcp/Controller/UserAccessKeyMcpAllowlistController.php` |
| Create | `src/Core/Framework/Mcp/Controller/UserMcpAllowlistController.php` |
| Modify | `src/Core/Framework/Mcp/McpException.php` (removed `missingCredentials()`) |
| Modify | `src/Administration/.../sw-users-permissions-user-detail.html.twig` |
| Modify | `src/Administration/.../sw-users-permissions-user-detail/index.js` |
| Modify | `src/Administration/.../sw-users-permissions/acl/index.js` |
| Modify | `src/Administration/.../sw-users-permissions/snippet/en.json` + `de.json` |
| Modify | `src/Administration/Resources/app/administration/src/core/service/api/user.api.service.js` |

## Known gaps / parking lot

- **Access key labels**: user access keys show raw `SWUA...` strings with no human-readable name. Considered but parked — out of scope for this workstream.
- **MCP-spec OAuth**: auth code + PKCE + DCR. Neither `mcp/sdk` nor `symfony/mcp-bundle` provide this. Tracked in separate gap doc.
