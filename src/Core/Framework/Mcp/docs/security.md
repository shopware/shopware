# MCP Server Security

## Authentication
The MCP endpoint at `/api/_mcp` is protected by Shopware's Admin API OAuth authentication. Every request must include a valid Bearer token or integration credentials (`sw-access-key` + `sw-secret-access-key` headers).

## ACL (Access Control)
Entity tools (`entity-search`, `entity-read`, `entity-upsert`, `entity-delete`) check the authenticated user's ACL permissions before executing. If the integration/user does not have the required privilege (e.g. `product:read`, `product:create`), the tool returns an error without touching the database.

The `RequestCriteriaBuilder` additionally validates field-level `ApiAware` flags on criteria fields.

**Tools without ACL checks:**
- `shopware-entity-schema` -- schema introspection only, no data access
- `shopware-console-command` -- restricted by the command allowlist, not by ACL roles
- `shopware-system-config-read` / `shopware-system-config-write` -- `SystemConfigService` has no built-in ACL; restrict via the tool allowlist
- `shopware-api-routes` -- route listing, read-only metadata
- `shopware-business-events` / `shopware-flow-actions` -- event/action listing, read-only metadata
- `shopware-storefront-search` -- uses its own `SalesChannelContext`, not the admin ACL

For these tools, use `shopware.mcp.allowed_tools` to control access.

## Tool allowlist
You can restrict which MCP tools are available by configuring `shopware.mcp.allowed_tools`:

```yaml
shopware:
    mcp:
        allowed_tools:
            - shopware-entity-schema
            - shopware-entity-search
            - shopware-entity-read
            - shopware-system-config-read
```

An empty list (default) means all tools are allowed. The allowlist is enforced at compile time by the `McpToolCompilerPass`.

## Console command allowlist
The `shopware-console-command` tool only executes commands listed in `shopware.mcp.allowed_console_commands`. Default allowlist:

- `cache:clear`, `cache:warmup`
- `plugin:list`, `plugin:refresh`
- `scheduled-task:list`
- `theme:compile`
- `debug:router`, `debug:mcp`
- `messenger:stats`
- `assets:install`

Plugins can add their commands to the allowlist by tagging with `shopware.mcp.allowed_command`.

## Tool name enforcement
All capability names must only contain `a-zA-Z0-9_-` (no dots). Use a hyphen-separated prefix for namespacing (e.g., `my-plugin-my-tool`).

## Dry-run safety
All write tools (`shopware-entity-upsert`, `shopware-entity-delete`, `shopware-system-config-write`, `shopware-state-machine-transition`) default to `dryRun=true`. This means:

- Changes are validated and previewed but **not persisted**
- For entity operations, a database transaction is opened, the operation runs, results are captured, then the transaction is rolled back
- The AI client must explicitly set `dryRun=false` to execute the operation

## App tool security
App MCP tool calls are signed with HMAC using the app's secret via `RequestSigner`. The app can verify the `shopware-shop-signature` header to ensure the request originates from the Shopware instance.

## Audit logging
Tool invocations via `shopware-console-command` are logged to the `mcp` Monolog channel, including command name, arguments, exit code, and duration.

## Rate limiting
The MCP endpoint uses per-integration rate limiting. Each set of integration credentials gets its own rate limit bucket.

## Feature flag
The entire MCP subsystem is behind the `MCP_SERVER` feature flag. When the flag is inactive, all MCP services are removed from the container at compile time, ensuring zero runtime overhead.
