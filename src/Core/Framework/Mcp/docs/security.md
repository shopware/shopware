# MCP Server Security

## Authentication
The MCP endpoint at `/api/_mcp` is protected by Shopware's Admin API OAuth authentication. Every request must include a valid Bearer token or integration credentials (`sw-access-key` + `sw-secret-access-key` headers).

## ACL (Access Control)
Entity tools (`entity-search`, `entity-read`, `entity-upsert`, `entity-delete`), `state-machine-transition`, and outcome tools (`order-summary`, `customer-lookup`, `product-create`, `revenue-report`) check the authenticated user's ACL permissions before executing. If the integration/user does not have the required privilege (e.g. `product:read`, `order:update`), the tool returns an error without touching the database. The `state-machine-transition` tool requires `{entity}:read` for dry-run and additionally `{entity}:update` for actual transitions.

**ACL requirements for outcome tools:**
- `shopware-order-summary` -- `order:read`
- `shopware-customer-lookup` -- `customer:read`
- `shopware-product-create` -- `product:create`, `product:read`, `tax:read`, `currency:read`
- `shopware-revenue-report` -- `order:read`
**Storefront tools (Store API context):**
- `shopware-cart-manage` -- No admin ACL; requires a valid sales channel ID
- `shopware-cart-checkout` -- No admin ACL; requires a valid sales channel ID and registered customer
- `shopware-checkout-methods` -- No admin ACL; requires a valid sales channel ID

The `RequestCriteriaBuilder` additionally validates field-level `ApiAware` flags on criteria fields.

**Tools without ACL checks:**
- `shopware-entity-schema` -- schema introspection only, no data access
- `shopware-console-command` -- restricted by the command allowlist, not by ACL roles
- `shopware-system-config-read` / `shopware-system-config-write` -- `SystemConfigService` has no built-in ACL; restrict via the tool allowlist
- `shopware-storefront-search` -- uses its own `SalesChannelContext`, not the admin ACL

For these tools, use `shopware.mcp.allowed_tools` to control access.

**Resources:** All MCP resources (e.g., `shopware://entities`, `shopware://sales-channels`) are read-only and have no ACL checks. They expose reference data only.

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
