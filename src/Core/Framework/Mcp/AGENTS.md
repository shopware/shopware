# Shopware MCP Server

## Overview
This module implements a Model Context Protocol (MCP) server for Shopware, enabling AI clients (e.g., Claude Desktop, Cursor) to interact with the Shopware platform through a standardized protocol.

## Status
**Experimental** -- marked `@experimental stableVersion:v6.8.0` (API may change until 6.8.0). Always enabled; there is no feature flag to toggle.

## MCP capabilities

The MCP protocol defines three capability types. Each serves a different purpose:

### Tools
Actions the AI client can invoke. Tools execute logic and return results. Think of them as API endpoints the AI can call.
- **Triggered by**: the AI client decides when to call them
- **Can have side effects**: yes (writes, deletes, command execution)
- **Examples**: `shopware-entity-search`, `shopware-entity-upsert`, `shopware-order-state`
- **Attribute**: `#[McpTool(name: '...', description: '...')]`
- **Implementation**: `__invoke()` returns a JSON string

### Prompts
Pre-written instructions the AI client can request to get context. Prompts help the AI understand the system before it starts working.
- **Triggered by**: the AI client requests them during setup or when it needs guidance
- **Can have side effects**: no, read-only text
- **Examples**: `shopware-context` -- explains the data model, criteria format, and best practices
- **Attribute**: `#[McpPrompt(name: '...', description: '...')]`
- **Implementation**: `__invoke()` returns an array of `['role' => '...', 'content' => '...']` messages

### Resources
Static data the AI client can read. Resources are identified by URIs and provide reference data without executing logic.
- **Triggered by**: the AI client reads them like files
- **Can have side effects**: no, read-only data
- **Examples**: `shopware://entities`, `shopware://sales-channels`, `shopware://state-machines`, `shopware://business-events`, `shopware://flow-actions`
- **Attribute**: `#[McpResource(uri: '...', name: '...', description: '...')]`
- **Implementation**: `__invoke()` returns `['uri' => '...', 'mimeType' => '...', 'text' => '...']`

### When to use which
| Need | Use |
|---|---|
| AI should be able to query/modify data | Tool |
| AI needs instructions on how to use the system | Prompt |
| AI needs static reference data (lists, schemas) | Resource |

## Tool discovery: three routes, one of them universal

The Admin API endpoint uses progressive disclosure: `tools/list` advertises only a small set (`shopware-tool-search`, `shopware-toolsets-list`, `shopware-toolset-enable`, plus anything the connection advertises up front or has enabled), not the full catalogue. There are three ways to reach a deferred tool, and they differ in what they demand of the client:

- **Connect-time selection (works on every client).** Naming toolsets in the MCP URL as `?toolsets=order,media` or `?toolsets=all`, on `/api/_mcp` and `/store-api/_mcp` alike, advertises those tools from the first `tools/list` of the connection. The only client behaviour this relies on is posting to the URL it was configured with, which is the Streamable HTTP transport itself. `McpRequestedToolsetResolver` reads the parameter off the main request, and `McpToolsetRegistry::advertisedToolsForNames()` validates it and resolves `all`. The result is paginated like any other list (`shopware.mcp.pagination_limit`, 50), so a client that ignores `nextCursor` sees the first page only — a constraint `?toolsets=all` can reach once enough apps contribute toolsets.
- **`shopware-toolset-enable` + `listChanged` (only for clients that re-read `tools/list`).** Enabling a toolset stores it on the session, advertises its tools, and emits a `tools/listChanged` notification. The notification is advisory: a client MAY refresh, and nothing obliges it to. claude.ai reads `tools/list` once per connection and ignores the notification, so an enable mid-conversation never becomes visible there. Claude Code re-lists but does not re-index its deferred-tool store ([claude-code#66084](https://github.com/anthropics/claude-code/issues/66084)). Treat this path as an optimisation for well-behaved clients.
- **`shopware-tool-search` inline definitions (only for tool-search-capable clients).** Search returns full tool definitions inline. This requires the client to promote inline results into its callable set. Anthropic expands a `tool_reference` only for tools already present in the request's top-level `tools` array, so a tool that was never advertised cannot be promoted this way. `tools/call` itself never blocks an allowlisted tool for being unadvertised, so the server never dead-ends, though a client that treats `tools/list` as the immutable callable set will loop. The admin `shopware-tool-search` result carries a `_meta.usage` hint pointing at the enable path.

Every registered tool belongs to a group, and group membership is the single source of truth for visibility. The `discovery` group holds the always-advertised meta-tools (`shopware-tool-search`, `shopware-toolsets-list`, `shopware-toolset-enable`) and is never an enable-able toolset; it is the only thing on a fresh `tools/list` of a connection without `?toolsets=`. Every other tool is **deferred** — advertised only once its toolset is enabled — so no domain tool can leak into the default surface and the model is forced through discovery. Core and plugin tools declare their group with `#[McpToolGroup]` at compile time (`McpToolDiscoveryCompilerPass` derives `shopware.mcp.advertised_tools` from the `discovery` group); app tools (loaded at runtime, so they carry no attribute) are grouped under their owning app's technical name via `AppMcpPrivilegeProvider::getAppToolGroups()`, so each app forms its own toolset. Anything still without a group falls to the `other` catch-all, which is itself an enable-able toolset so that no allowlisted tool is ever reachable through `shopware-tool-search` alone.

The `discovery` group is reserved for the three meta-tools (`McpToolsetRegistry::DISCOVERY_META_TOOLS`). A plugin or bundle tool that claims it is moved to `other` at compile time, `bin/console debug:mcp` lists it with a warning, and the `shopware.mcpReservedToolGroup` PHPStan rule reports it during development. An extension that needs its tools on the first `tools/list`, for example for a proxy endpoint, selects its own toolset with `?toolsets=` instead, as agentic-commerce does on `/ucp/mcp`.

### Toolset descriptions

`shopware-toolsets-list` shows one description per toolset, and a model reads it to decide which toolset to enable.
Set it with the optional `description` argument of `#[McpToolGroup]`:

```php
#[McpTool(name: 'swag-report-revenue', description: '...')]
#[McpToolGroup('swag-report', description: 'Revenue, bestseller and customer reports for a date range. Enable it for questions about sales figures.')]
class RevenueReportTool
```

- Describe the toolset, not the tool: which tasks it covers and when to enable it.
  Keep it to one or two sentences, because it is sent on every `shopware-toolsets-list` call.
- Set it on one tool of the group.
- Extensions may add tools to a core group, such as `entity`, but cannot change its description.
  A description from a core bundle (`Shopware\Core`, `Shopware\Storefront`, `Shopware\Administration`, `Shopware\Elasticsearch`) always wins for its group, and an extension description for that group is ignored and logged at container build.
  Separately versioned plugins such as Commercial count as extensions.
- An extension can describe only a group no core tool describes.
  If two extensions describe the same group differently, the tool whose name sorts first wins and the conflict is logged.
- Without a description the toolset gets a generated one (`Tools explicitly assigned to the "<group>" MCP tool group.`), which gives a model little to route on.
- A tool that claims the reserved `discovery` group loses its description together with the group.
- App toolsets (`mcp.xml`) cannot carry a description yet; they always get the generated one.

`McpToolAnalysisCompilerPass` collects the descriptions per scope into `shopware.mcp.tool_group_descriptions` and `shopware.store_api_mcp.tool_group_descriptions`, and `McpToolsetRegistry` puts them into the toolset entries that `shopware-toolsets-list` and `shopware-toolset-enable` return.

The Store API endpoint (`/store-api/_mcp`) uses the same progressive disclosure via its own toolset meta-tools (`StoreApiToolsetsListTool` / `StoreApiToolsetEnableTool`) and a `store-api` toolset group, and its `shopware-tool-search` carries the same `_meta.usage` hint. Both endpoints' server `instructions` (returned in every `initialize` response) point clients at `shopware-tool-search` as the discovery entry point when no advertised tool matches the requested action.

## Architecture
- **Transport**: HTTP via Symfony MCP Bundle (`/api/_mcp`), authenticated through Shopware's Admin API OAuth stack
- **Context**: `McpContextProvider` bridges the authenticated HTTP request into the MCP tool execution layer
- **Tools**: Single-responsibility PHP classes with `#[McpTool]` attributes, registered via PHP service definitions (`mcp.php`)
- **Tool results**: the wire format of a tool result is decided only in `McpToolResultRenderer`, never in a tool. See "What the client receives" in `Tool/AGENTS.md`
- **Availability**: always enabled (no feature flag); services are present whenever `symfony/mcp-bundle` is installed

### List change notifications

Clients learn about changed tool, prompt and resource lists through `notifications/*/list_changed`. The notification is never sent live: `McpListChangedNotifier` appends it to the session's outgoing queue in the session store, and the client receives it with its next request.

- **Installation-wide changes are pulled, not pushed.** An app install, update, activation, deactivation or removal calls `McpListChangedNotifier::notify()`, which increments one version per changed list in the `mcp_list_version` table (`McpListVersions`). After every Admin API MCP request, `McpServerController` calls `syncSession()`, which compares those versions with the ones stored in the session data (`shopware_list_versions`) and queues `list_changed` for the lists that moved. A new session starts at the current versions.
- **Session-local changes are queued directly.** `shopware-toolset-enable` sets `McpListChangedNotifier::PENDING_TOOLS_LIST_CHANGED_ATTRIBUTE`, and the controller calls `notifySession()` for that session only.
- **Both run after `Server::run()`**, because the SDK saves its in-memory session at the end of the run and would otherwise overwrite the queued notification.
- **Only the Admin API server compares versions.** App capabilities are only registered there, so the Store API notifier has no `McpListVersions` and only handles toolset enables.

Invariants:
- No state per server and no list of session ids. Every server reads the same versions from the database and writes only to the session store the SDK already uses, so this works with any session store and any number of servers, without a shared lock. The registry of session ids that this replaced had to be shared exactly as widely as the session store, which a compiler pass could only guess (#21284).
- A notification costs one write per change; checking costs one small read per request, plus a session write only when something changed.
- Don't add a mechanism that pushes to all sessions. It needs the list of sessions again. On the stateless era, clients read the SDK notification bus through `subscriptions/listen`, and the versions feed that bus (see `adr/2026-09-24-mcp-stateless-era-degradation-policy.md`).

## Naming convention
All capability names use hyphen-separated prefixes (`a-zA-Z0-9_-` only, no dots):
- **Core**: `shopware-{name}` (e.g., `shopware-entity-search`, `shopware-entity-upsert`)
- **Plugin**: `{plugin-name}-{capability-name}` (e.g., `swag-admin-users-list-admins`)
- **App**: `{app-name}-{capability-name}` (e.g., `my-erp-sync-orders`)

`McpToolDiscoveryCompilerPass` enforces unique names per server and throws on conflicts. The `shopware-` prefix is reserved for core tools; `AppMcpToolLoader` skips app tools whose computed name starts with `shopware-`.

## Folder structure
- `AllowList/` -- Per-integration capability allowlist (`McpAllowlistProvider`, `McpAllowlistFilter`, `McpAllowlist`)
- `Attribute/` -- MCP-specific PHP attributes (`#[McpToolDependsOn]`, `#[McpToolRequires]`)
- `Authentication/` -- MCP authentication and exception listeners, `McpPrincipalResolver` (the principal of a request: `user-<id>`, `integration-<accessKey>`, `integration-<accessKey>-user-<id>`)
- `Context/` -- Context bridging (`McpContextProvider`, `StoreApiMcpContextProvider`)
- `Controller/` -- HTTP endpoints for MCP protocol (`McpServerController` admin, `StoreApiMcpServerController` store)
- `RateLimit/` -- `McpRateLimiter` wrapper around the core `RateLimiter` (per-scope keys + throttle translation)
- `Session/` -- Session helpers: `McpSessionIdValidator` (rejects malformed `mcp-session-id`), `McpSessionPrincipalBinding` (binds an Admin API session to the principal that sent `initialize`; another principal, or a session without one, gets the unknown-session `404`), `McpSessionCleanupSubscriber` (wipes the tool results a session stored and its toolset rows on session DELETE)
- `ScheduledTask/` -- Periodic GC: `McpToolsetSessionCleanupTask` (session-store liveness for `mcp_toolset_session`), `McpToolResultCacheCleanupTask` (age TTL for `mcp_tool_result_cache`; does not require a session store)
- `Tool/` -- Individual MCP tool implementations
- `Prompt/` -- System prompts for AI context
- `Resource/` -- Static MCP resources
- `Command/` -- CLI commands (`debug:mcp`)
- `Loader/` -- Extension loaders for app capabilities (`AppMcpToolLoader`, `AppMcpPromptLoader`, `AppMcpResourceLoader`, `AppMcpCapabilityExecutor`, `AbstractAppMcpLoader`)
- `docs/` -- Documentation: tool reference, examples, security, setup, extensibility, user stories

## Availability

The MCP server is **no longer feature-flag gated** — it is always enabled. Classes remain marked `@experimental stableVersion:v6.8.0`, so the API may still change until 6.8.0, but there is no `MCP_SERVER` flag to toggle.

The `=== null` null-checks on injected `mcp.*` dependencies in controllers/commands are a safety net for "`symfony/mcp-bundle` truly absent" (they resolve to `null` via `nullOnInvalid()`), **not** a feature-flag substitute. Because the bundle is in `require` and registered unconditionally in `config/bundles.php`, they never resolve to `null` in practice; the guards will be removed once MCP is stable (v6.8.0).

## Conventions
- All classes use `@experimental stableVersion:v6.8.0` annotation
- All classes use `#[Package('framework')]` attribute
- Tools return JSON strings; the MCP protocol handles transport encoding
- Write tools default to `dryRun=true` for safety. Dry-run adds `SKIP_TRIGGER_FLOW` to the context to prevent Flow Builder actions during preview
- Entity tools validate entity existence with `registry->has()` before ACL checks to provide clear error messages
- Service IDs use FQCN; tags include `mcp.tool` for SDK discovery
- Tools declare prerequisites with `#[McpToolDependsOn('other-tool-name')]` (repeatable) — the allowlist UI auto-expands these when a user enables a tool; `debug:mcp` shows them in the Dependencies column
- Tools declare required ACL privileges with `#[McpToolRequires]` (repeatable) — **declarative only**; runtime enforcement still depends on `requirePrivilege()` calls and DAL ACL checks. The attribute is used by `debug:mcp` (Privileges column), the API (`/_action/mcp/tools`), and the Admin UI to help operators configure roles correctly
- Privileges are declared, see "Declaring privileges" below

### Declaring privileges

Every Admin API tool declares exactly one of two attributes on its class.
`#[McpToolRequires]` lists the ACL privileges it needs.
`#[McpToolRequiresNoPrivilege(reason: '...')]` is the explicit opt-out for a tool that reads no shop data, such as `shopware-entity-schema` and the three discovery meta-tools.
Both are informational and grant nothing: the opt-out is not shown in `debug:mcp` or the capabilities API, a tool with it simply has no privileges listed, and the DAL still checks the ACL of every repository call.

Store API tools declare neither ACL attribute nor call `requirePrivilege()` or `requirePrivilegeResult()`, because Admin ACL privileges do not exist in a sales channel context.

PHPStan enforces this with two rules:

- `shopware.mcpAdminToolMissingRequires` (`McpAdminToolRequiresPrivilegeRule`) reports an Admin API tool with neither attribute, or with both.
- `shopware.mcpStoreApiToolAdminAcl` (`McpStoreApiToolAdminAclRule`) reports a Store API tool with `#[McpToolRequires]` or a `requirePrivilege()` or `requirePrivilegeResult()` call in its own class.
  Inherited calls are not followed. They are harmless, because `requirePrivilege()` always passes outside an Admin API context.

Both read the server from the container tags (`McpToolScopeResolver`), one service definition at a time.
A class registered on both servers gets both rules, so it can only use `#[McpToolRequiresNoPrivilege]`.
A class the container does not know is skipped.

### Tool metadata: `meta` vs dedicated attributes

Avoid adding a new PHP attribute for every MCP tool hint. Choose the smallest representation that keeps the concept clear and maintainable:

- Use `#[McpTool(..., meta: [...])]` for lightweight MCP descriptor hints that are simple scalar values, experimental, client-facing, or only consumed near tool discovery/advertisement. Examples: ranking/search hints, visibility hints. (Advertisement visibility itself is **not** a `meta` hint — it is derived from the `#[McpToolGroup]`; the `discovery` group is advertised, everything else is deferred.)
- Use a dedicated Shopware attribute only when the concept is first-class in Shopware, needs structured typing or validation, is repeatable, is consumed by several subsystems, or should be discoverable without parsing arbitrary string keys. Examples: `#[McpToolDependsOn]` and `#[McpToolRequires]`.
- Do not duplicate the same concept in both places. If `meta` grows validation rules, multiple consumers, or cross-cutting behavior, consider promoting it to an attribute in a follow-up. If an attribute is just a single optional scalar with one consumer, prefer `meta` instead.
- Before adding a new attribute, document why `meta` is not enough in the PR description or nearby tests. Attribute sprawl makes tool declarations harder to scan.

## Validating capabilities are loaded

Two things have to hold: the service carries the DI tag, and it is assigned to a server.

### One layer: the DI tag
The DI tag is all that is required, for core, in-tree bundle, plugin and third-party bundle tools alike. The MCP bundle collects every service tagged `mcp.tool` at container compile time, reads its `#[McpTool]` attribute and registers it on a server. There is no directory scanning: `scan_dirs` was removed when the bundle replaced the SDK's file-based discovery with compile-time container registration (mcp-bundle 0.12).

Plugin lifecycle is respected: if the plugin is inactive the service is absent from the container and the tool is not registered.

### Which server a tool lands on
Each MCP server declares in `packages/mcp.php` which capabilities it exposes, as namespace prefixes — `Shopware\Core\Framework\Mcp\` and `Shopware\Storefront\Mcp\` for the Admin API server, `Shopware\Core\System\SalesChannel\Mcp\` for the Store API one. A capability outside those namespaces (any plugin or third-party bundle) cannot be named by a prefix, so `McpToolDiscoveryCompilerPass` assigns it explicitly: it appends the class to the bundle's `mcp.servers.elements` parameter, plugin capabilities going to the Admin API server. A capability assigned to no server is silently absent; both `bin/console debug:mcp` (in its footer) and `bin/console debug:mcp --native` report those.

### Verification methods

| Method | What it covers | When to use |
|---|---|---|
| `bin/console debug:mcp` | Both registries (admin + store-api) — same source as the HTTP endpoints | Quick manual check during development |
| `bin/console debug:mcp --native` | The MCP bundle's own view: configured servers and clients, prompts and resources, and capabilities assigned to no server | Checking a prompt or resource, or why a capability does not show up |
| `McpCapabilityDiscoveryTest` | HTTP → `tools/list` (full kernel) | CI — authoritative end-to-end check |
| `McpServiceRegistrationTest` | DI layer only | Fast integration-level guard that every MCP service is registered in the container |

`bin/console debug:mcp` uses the same `Registry` instances as the HTTP endpoints (populated by calling `Builder::build()` per scope), so it shows core tools, plugin tools, app tools, and Store API tools in one view, grouped per endpoint. It lists tools only, with the Shopware data the bundle's command has no equivalent for; prompts and resources are listed by `--native`. Use `--scope=api` or `--scope=store-api` to narrow it to one endpoint.

**`McpCapabilityDiscoveryTest`** (`tests/integration/Core/Framework/Mcp/McpCapabilityDiscoveryTest.php`) boots the full kernel, authenticates, and calls the live MCP HTTP endpoint. It is the authoritative check that mirrors what the MCP Inspector does interactively. Add new capability names to its `expectedTools()` / `expectedPrompts()` / `expectedResources()` lists when adding new core capabilities.

## Extensibility
- **Plugins**: Tag services with `shopware.mcp.tool` -- `McpToolDiscoveryCompilerPass` re-tags them as `mcp.tool` and assigns them to the Admin API server, so they appear in both `debug:mcp` and the HTTP endpoint. Use `McpToolResponse` for consistent error handling and response formatting.
- **Third-party Symfony bundles**: Same `shopware.mcp.tool` tag mechanism as plugins -- `McpToolDiscoveryCompilerPass` handles it. See `custom/bundles/SwagMcpExampleBundle/` for a worked example.
- **Apps**: Declare capabilities in `Resources/mcp.xml` -- parsed and stored in the generic `app_feature` table by the respective feature definition (`McpToolFeatureDefinition`, `McpPromptFeatureDefinition`, `McpResourceFeatureDefinition`), loaded at runtime by the corresponding Loader (`AppMcpToolLoader`, `AppMcpPromptLoader`, `AppMcpResourceLoader`). App tool webhook payloads include `shopId` and `appVersion` in the `source` object. **App tools also support internal dispatch via `/api/script/{path}` -- see the Serverless app tools section below.**
- **In-tree Shopware bundles** (Storefront, etc.): Tag with **`mcp.tool`** directly (not `shopware.mcp.tool`), and make sure the class sits under a namespace the Admin API server's `registry` prefixes in `packages/mcp.php` cover -- otherwise add it there.
- **Reserved prefix**: The `shopware-` prefix is reserved for core tools. App tools with names starting with `shopware-` are skipped during loading.

## Serverless app tools (app scripts)

App MCP tools can use `/api/script/{path}` as their `url` — Shopware dispatches these as internal subrequests, removing the need for an external server or `<setup>` registration handshake.

### How it works

1. `AppMcpCapabilityExecutor` detects URLs starting with `/` and dispatches a Symfony subrequest instead of a Guzzle HTTP call.
2. Arguments are passed as a `POST` form parameter named `arguments` (not JSON body), so Twig scripts can access them via `hook.request.request.all('arguments')`.
3. Auth headers from the parent MCP request are inherited — the subrequest runs in the integration's authenticated context, so DAL ACL is enforced normally.
4. `AppMcpToolLoader` includes apps without a secret when their tool URL starts with `/`.

### App script pattern

In `manifest.xml`, no `<setup>` block is needed. Declare entity permissions normally in `<permissions>`.

In `Resources/mcp.xml`, set `url="/api/script/{path}"` and optionally declare `<required-privileges>`:

```xml
<mcp-tool name="my-tool" url="/api/script/my-app-my-tool">
    <label>My Tool</label>
    <required-privileges>
        <privilege>product:read</privilege>
        <privilege>order:read</privilege>
    </required-privileges>
</mcp-tool>
```

Create the Twig script at `Resources/scripts/api-my-app-my-tool/script.twig`:

```twig
{% block response %}
    {% set args = hook.request.request.all('arguments') ?? {} %}
    {% set response = services.response.json({ success: true, data: { ... } }) %}
    {% do hook.setResponse(response) %}
{% endblock %}
```

### Caching

**Always use `/api/script/` (Admin API), never `/store-api/script/`.** Admin API scripts are POST-only, auth-required, and never HTTP-cached — correct for MCP tools where AI agents must receive current data. Store API scripts use Shopware's HTTP cache layer; if you must use one for MCP, add `{% do response.cache.disable() %}` before `hook.setResponse()` to prevent stale responses.

### Required privileges (`<required-privileges>`)

`<required-privileges>` in `mcp.xml` is the app-side equivalent of `#[McpToolRequires]` for plugins. It is **informational** — the Admin UI shows the declared privileges as chips in the integration allowlist and warns when the integration role is missing them. Actual enforcement happens via DAL ACL inside the script.

`McpToolFeatureDefinition` validates at install/update time that every declared privilege appears in the app's manifest `<permissions>`. An app that declares `product:read` as a required privilege but does not have `<read>product</read>` in `<permissions>` will fail to install with a clear error message. An app without a `<permissions>` block has no privileges, so any required privilege fails the same way.

## Future ideas / backlog

### ACL / visibility
- **Filter `shopware://entities` resource by ACL** — `EntityListResource` currently returns all registered entities regardless of the caller's permissions. It should inject `McpContextProvider` and filter by `$context->isAllowed($entity . ':read')`, with a null-safe fallback for CLI/system contexts (return full list when there is no HTTP request).
- **`debug:mcp` entity visibility** — when `--integration SWIA...` is passed, add an "Entities" count column to the tools table (how many entities that integration can read for entity-tools). In the detail view (`debug:mcp shopware-entity-read --integration ...`), show the full sorted list of accessible entity names.

### Rate limiting
The two MCP endpoints are rate-limited via the core `RateLimiter` through `McpRateLimiter` (`RateLimit/McpRateLimiter.php`), which owns the throttle/`McpException::throttled()` translation and the per-scope key derivation. Each scope has its own config route: `mcp_admin_api` (keyed per principal via `McpPrincipalResolver`, so a token refresh keeps the bucket; client IP as fallback; generous) and `mcp_store_api` (keyed per sales-channel context token, tighter because it is public and the key is rotatable). Both are defined in `shopware.yaml` under `shopware.api.rate_limiter`.

Open improvements:
- **Per-tool rate limiting** — the current limit is per-endpoint: a cheap `tools/list` and an expensive `entity-upsert`/`entity-delete`/`media-upload`/`system-config-write` draw from the same bucket. The industry consensus for MCP servers is to bucket cheap reads (`entity-search`, `entity-schema`) high (~100-200/min) and expensive/mutating tools low (~5-30/min). This needs a per-tool key (e.g. derive the tool name from the JSON-RPC body, which `McpServerController` already parses into `ATTRIBUTE_JSONRPC_BODY`) and a route per cost class.
- **`Retry-After` header** — `McpException::throttled()` returns HTTP 429 with the wait time in the message/parameters, but no `Retry-After` response header. Adding it (and a structured `retry_after` in the JSON-RPC error) lets well-behaved agent clients back off correctly instead of blindly retrying.
- **Policy.** Both routes use `sliding_window` (300 requests per minute on the Admin API, 120 on the Store API). Do not switch them back to `time_backoff`: that policy is a lockout for repeated attempts (login, password recovery). Once its first limit is used up it accepts one request per interval and never lowers the count until `reset`, so a client that sent 300 requests got HTTP 429 on almost every following call, including the handshake (#18906). `McpRateLimiterPolicyTest` runs the shipped limits through a real limiter.

### Store API / shopper-side MCP
The current MCP server is admin-API only (`/api/_mcp`, integration key auth). There is no MCP endpoint for the Store API.

A store-API MCP would authenticate as a **customer** and expose the buyer journey (browse, cart, checkout, account). This is a fundamentally different security model from the admin MCP (operator/developer automation). The current `merchant-cart-*` / `merchant-storefront-search` tools from the merchant assistant app are a pragmatic middle ground — they run under admin auth but proxy to Store API internally on behalf of a customer context.

Open questions before implementing:
- Auth: customer session token, guest token, or a new MCP-specific customer credential?
- Scope: read-only browse vs. full cart/checkout mutations?
- Overlap: when does the admin MCP + store-API proxy (current approach) become insufficient vs. needing a real customer-scoped MCP?

### SDK-ready features (no upstream changes needed)
symfony/mcp-bundle and mcp/sdk (see `composer.json` for the versions in use) already implement the following — Shopware just needs to wire them up:

- **Resource subscriptions** — SDK has `ResourceSubscribeHandler` and `ResourceUnsubscribeHandler` (`vendor/mcp/sdk`). Resource templates (`#[McpResourceTemplate]`) are already wired up in core — see `ToolResultResource` and `Resource/AGENTS.md`. Subscriptions remain to be wired up if clients need push notifications when resources change.
- **Protocol-level pagination** — `RegistryInterface::getTools(?int $limit, ?string $cursor)` etc. already support cursor-based pagination; bundle has a `mcp.pagination_limit` config param. Shopware doesn't configure or expose it — relevant once tool/resource counts grow large.
- **Completion utility** — SDK has `CompletionCompleteHandler` + `CompletionProvider` interface with built-in `EnumCompletionProvider` / `ListCompletionProvider` (`vendor/mcp/sdk/src/Capability/Attribute/CompletionProvider.php`). Register providers on tool/prompt arguments to power autocomplete in MCP clients (e.g. entity name suggestions for the `entity` parameter on entity tools).

---

## Authentication

OAuth is an additional way in.
Access keys, integration tokens, password grant tokens and authorization code tokens without `resource` keep working unchanged.
The design is recorded in `adr/2026-10-21-mcp-oauth-client-authentication.md`.

### Discovery

- An unauthenticated or rejected request to `/api/_mcp` gets `401` with the JSON-RPC `-32001` body and `WWW-Authenticate: Bearer resource_metadata="<base>/.well-known/oauth-protected-resource/api/_mcp"`.
  When credentials were sent, the header adds `error="invalid_token"`.
  `McpExceptionListener` sets it on the Admin API route `api.mcp.endpoint` only.
- A query on the MCP URL (`?toolsets=`) is kept as sent: the challenge URL is `.../.well-known/oauth-protected-resource/api/_mcp?<query>` (RFC 9728 §3.1), and the metadata answers with `resource` `<base>/api/_mcp?<query>`.
  RFC 9728 §3.3 makes clients reject a `resource` that differs from the URL they used.
  The audience check (`OAuthResource::normalize()`) keeps ignoring the query, so the same token works with and without toolsets.
- `McpOAuthMetadataController` serves the protected resource metadata (RFC 9728) at `/.well-known/oauth-protected-resource/api/_mcp` and the authorization server metadata (RFC 8414) at `/.well-known/oauth-authorization-server/api`.
  The issuer is `<base>/api`; the authorization response carries `iss` (RFC 9207).
- `McpOAuthMetadata` builds the URLs and documents.
  `<base>` keeps the installation base path.
  It comes from the request when `McpAllowedHostsProvider` allows the host, otherwise from `APP_URL`.
  The protected resource metadata is the `mcp/sdk` value object `ProtectedResourceMetadata`; the authorization server metadata (RFC 8414) stays ours, the SDK has no class for it.
  Only the value object is used, not `ProtectedResourceMetadataHandler` or its middleware: the document depends on the request host, base path and query, and the routes stay Symfony routes.
  Since `mcp/sdk` 0.9 the class rejects plain http outside loopback hosts (`localhost`, `127.0.0.1`, `::1`); the route then answers `404` with `MCP__OAUTH_PROTECTED_RESOURCE_METADATA_UNAVAILABLE`.
  Dev lanes on `http://<name>.localhost` are affected until the SDK accepts `*.localhost` as loopback ([php-sdk#620](https://github.com/modelcontextprotocol/php-sdk/pull/620)); use `http://localhost:<port>` meanwhile.
- The routes have no route scope (`McpOAuthMetadataRouteScopeWhitelist`) and are exempt from the sales channel lookup in `RequestTransformer`.
- Well-known URIs sit at the host root with the path inserted.
  An installation in a subdirectory (`https://shop.example/shop`) needs a web server rewrite, for example in nginx:

  ```nginx
  location = /.well-known/oauth-protected-resource/shop/api/_mcp { rewrite ^ /shop/.well-known/oauth-protected-resource/api/_mcp last; }
  location = /.well-known/oauth-authorization-server/shop/api { rewrite ^ /shop/.well-known/oauth-authorization-server/api last; }
  ```

### Tokens bound to the MCP resource

- `resource` (RFC 8707) is accepted on `GET`/`POST /api/oauth/authorize` and `POST /api/oauth/token`.
  The only accepted value is this shop's MCP resource (`OAuthResource::normalize()` ignores case of scheme and host, default ports, a trailing slash and the query); a fragment or anything else is `invalid_target`.
  The token request must name the same resource as the authorization request, and a refresh keeps it.
  A token request without `resource` still gets a token bound to the granted resource, as RFC 8707 allows.
- The resource is stored in `oauth_auth_code.resource` and `refresh_token.resource`.
  The JWT keeps the client id as the first `aud` entry and lists the resource after it.
- `SymfonyBearerTokenValidator` rejects a bound token with `401` unless the caller passes `acceptResourceBoundToken: true`.
  Only `ApiAuthenticationListener` does, for the main request to `api.mcp.endpoint` and for subrequests carrying `OAuthResource::SUBREQUEST_ATTRIBUTE`.
  `AppMcpCapabilityExecutor` sets that attribute on the app script subrequests of a request authenticated with a bound token.
  Any other subrequest rejects the token, because ESI fragments copy the parent's `Authorization` header.
  Services that validate bearer tokens themselves (system health check, Administration snippets) inherit the rejection.
  `SymfonyBearerTokenValidatorConsumerTest` lists every service using the validator and fails when one is added; review it for this rule.
- The binding separates the MCP endpoint from the rest of the Admin API, not one host from another.
  A token issued for the MCP resource of one allowed host is accepted on the MCP endpoint of every other allowed host of the same installation, because it is the same user with the same permissions.
  The validator does not compare the bound resource with the requested host; keep it that way unless hosts of one installation get separate principals.
- The consent page forwards `resource` to `GET /api/oauth/authorize/info` and `POST /api/oauth/authorize`, and the info answer returns it so the page says that access is limited to the MCP endpoint.
  An Administration built before resource support drops it: the code is stored without resource and the token request fails with `invalid_target` and a hint to rebuild the Administration.
- A bound token authenticates its user like any other token of that user; MCP sessions and the rate limit treat it no differently.
  Binding sessions to the principal and keying the rate limit on it are the subject of the related PRs https://github.com/shopware/shopware/pull/21860 and https://github.com/shopware/shopware/pull/21861.

### Adding an OAuth client

There is no dynamic client registration.
`shopware-cli` is the only default client.
Redirect URIs on the loopback IP literals `http://127.0.0.1` and `http://[::1]` match any port; every other redirect URI, including `http://localhost:<port>`, must match exactly.
Web redirect URIs must use `https`.
Plain `http` is accepted for `localhost` and the loopback IP literals only, or for every host when the kernel runs in the `dev` environment.
Private-use schemes of native apps (`cursor://`) stay allowed and match exactly.
`PublicClientRegistry::isTransportAllowed()` holds the rule; `ShopwareAuthCodeGrantType` rejects other redirect URIs on authorize with `FRAMEWORK__OAUTH_INSECURE_REDIRECT_URI` (`400`, not sent to the redirect URI).
The check runs on authorize and not on container build, because `oauth_clients` already shipped and a single bad entry must not break the whole Admin API.
An operator adds a client for an MCP client with a configurable client id in `config/packages/shopware.yaml`.
Claude Code calls back on `http://localhost:<port>/callback`, so register a fixed port and pass it with `--callback-port`:

```yaml
shopware:
    api:
        oauth_clients:
            claude-code:
                name: 'Claude Code'
                redirect_uris:
                    - 'http://localhost:53682/callback'
```

```shell
claude mcp add --transport http --client-id claude-code --callback-port 53682 shopware https://shop.example/api/_mcp
```

Claude Code refuses plain `http` token endpoints on hosts other than `localhost`, `127.0.0.1` and `::1`, so a dev host such as `trunk.localhost` needs `https`.

Known: the root `/.well-known/oauth-authorization-server` belongs to the UCP SDK (route `ucp_sdk_symfony_oauth_metadata`, `501` while identity linking is disabled).
MCP clients use the path form `/.well-known/oauth-authorization-server/api` and are not affected.

## Security

Every MCP request passes through three layers in order:

1. **Authentication**: every Admin API credential: integration or user access keys (`sw-access-key` + `sw-secret-access-key`), or a bearer token from any grant, including OAuth tokens bound to the MCP resource. See Authentication above.
2. **Per-principal capability allowlist** — each integration and each user stores a `mcp_allowlist` JSON object with `tools`, `resources`, and `prompts` keys. Configured via Settings → Integrations → Edit MCP Allowlist, and on the user detail page. `tools/list`, `resources/list`, and `prompts/list` responses are filtered; `tools/call`, `resources/read`, and `prompts/get` are rejected early with a clear error. Tool allowlist auto-expands transitive `#[McpToolDependsOn]` dependencies.
   **The selection must be explicit.** `McpAllowlist::restrictedFromJson()` resolves every value that is not an explicit list to an empty list, i.e. deny all: an unset column, an empty column, unparseable JSON, JSON that is not an object, a missing per-type key, an explicit `null` per-type value, and a per-type value that is not a JSON list (a string, or an object — which `json_decode` would otherwise hand over as an associative array of names). The save endpoints reject the object shape outright rather than storing a selection that reads back empty. **The only bypass is an administrator user** (`user.admin = 1`), for whom `McpAllowlistProvider::forUserId()` returns `McpAllowlist::unrestricted()`. An integration never bypasses the allowlist, not even one flagged `admin`; the `admin` flag still bypasses layer 3 (ACL).
   Note that resources are allowlisted by **URI** (`shopware://currencies`) while `resources/list` reports the **name** (`shopware-currencies`). Tools and prompts use the name for both.
   **Scope**: resolved for every auth mode `McpAllowlistProvider::forCurrentRequest()` recognises — integration access key, user access key, bearer token from either grant, and a delegated `sw-app-user-id` request, where the integration and user allowlists are intersected. A request whose principal cannot be resolved at all is treated as having no selection, so the endpoint fails closed.
   **Exception**: the discovery meta-tools `shopware-tool-search`, `shopware-toolsets-list` and `shopware-toolset-enable` stay advertised and callable for every principal (`McpAllowlistListRequestHandler::DISCOVERY_META_TOOLS`). They surface only capability names the effective allowlist already permits, so for a principal with no selection they resolve to nothing.
   Parsing rules are pinned by `tests/unit/Core/Framework/Mcp/AllowList/`; the end-to-end behaviour per auth mode by `tests/integration/Core/Framework/Mcp/McpAllowlistEnforcementTest.php`.
3. **ACL / Privileges** — tools call `requirePrivilege()` before touching data. Missing privileges return `{"success": false, "error": "Missing privilege: ...", "code": "missing_privilege"}` (single canonical prefix — use `McpToolResponse::missingPrivilegesError()`, never a hand-rolled message). Entity tools that accept criteria JSON additionally validate the built `Criteria` with `AclCriteriaValidator` (same association ACL model as the Admin API), so reading, filtering, or aggregating over an association also requires the associated entity's `:read` privilege. Tools may also annotate their static requirements with `#[McpToolRequires]` so operators can configure roles correctly upfront — but this is informational only and does not replace the `requirePrivilege()` check.

Additional safeguards:
- **Rate limiting**: every request passes through `McpRateLimiter` before the protocol runs. Separate per-scope buckets (`mcp_admin_api`, `mcp_store_api`); exceeding the limit returns HTTP 429 via `McpException::throttled()`. See the Rate limiting section under "Future ideas / backlog" for the keying details and open improvements.
- **Audit logging**: tool invocations logged via `mcp` Monolog channel
- **App HMAC**: app tool calls signed with `RequestSigner` using the app secret
- **Signed result pointers**: `McpToolResultPointerSigner` signs the pointers in tool result links (`<id>.<expiry>.<hmac>`, bound to the principal). It builds on the shared `Shopware\Core\Framework\Util\HmacSigner` (injected clock, constant-time compare, expiry). Use that helper for any new short-lived token Shopware signs and reads back itself instead of calling `hash_hmac` directly; keep a purpose label in the signed data and validate the fields when parsing.
- **XML parsing**: `mcp.xml` parsed with `XmlUtils::loadFile()` to prevent XXE attacks
- **Entity validation**: entity tools check `registry->has()` before ACL to give clear "entity not found" errors
- **Global compile-time allowlist**: `shopware.mcp.allowed_tools` acts as an installation-wide safety switch (secondary to per-integration allowlists)
- **Error visibility**: `McpExceptionListener` converts exceptions on the MCP route to JSON-RPC error responses instead of HTML. It also handles `POST /register` — some clients (e.g. Cursor) fall back to that path when the primary connection fails; without the listener they receive an HTML 404 or a storefront redirect that hides the real error. The gate is `POST` method (not `Accept: application/json`) since browser navigation to a register page uses GET.

## Admin UI — integration list

App integrations (created on app install via `AppLifecycle::enrichInstallMetadata`) appear in Settings → Integrations with a "Managed by App" badge. Edit and Delete are disabled for them; only the MCP tool allowlist is editable.

**ACL privileges:**
- `integration.editor` — gates creating/editing/deleting manual integrations (label, keys, ACL roles)
- `integration_mcp.editor` — gates editing the MCP capability allowlist for any integration. Declared under `additional_permissions` in `acl/index.js`. Depends on `integration.viewer`.

**Saving the allowlist** uses a dedicated endpoint `POST /api/_action/integration/{id}/mcp-allowlist` (controller: `IntegrationMcpAllowlistController`). Body: `{ allowlist: {tools, resources, prompts} | null }`. This avoids the changeset generator recursing into the `app` one-to-one association (which has no `_origin` when loaded via criteria) and provides a clean ACL boundary separate from `integration:update`.

**App deactivation**: when an app is deactivated via `AppLifecycle::deactivate()`, its integration is soft-deleted (`deletedAt` set). This suspends MCP authentication for that integration — the DAL excludes soft-deleted rows, so token requests fail. Reactivating the app (`activate()`) clears `deletedAt` and restores access.
