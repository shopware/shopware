# Shopware MCP Server

## Overview
This module implements a Model Context Protocol (MCP) server for Shopware, enabling AI clients (e.g., Claude Desktop, Cursor) to interact with the Shopware platform through a standardized protocol.

## Status
**Experimental** -- gated behind the `MCP_SERVER` feature flag. Use `MCP_SERVER=1` environment variable to enable.

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

## Architecture
- **Transport**: HTTP via Symfony MCP Bundle (`/api/_mcp`), authenticated through Shopware's Admin API OAuth stack
- **Context**: `McpContextProvider` bridges the authenticated HTTP request into the MCP tool execution layer
- **Tools**: Single-responsibility PHP classes with `#[McpTool]` attributes, registered via PHP service definitions (`mcp.php`)
- **Feature flag**: All services tagged with `shopware.feature` flag `MCP_SERVER` -- removed from the container when disabled

## Naming convention
All capability names use hyphen-separated prefixes (`a-zA-Z0-9_-` only, no dots):
- **Core**: `shopware-{name}` (e.g., `shopware-entity-search`, `shopware-entity-upsert`)
- **Plugin**: `{plugin-name}-{capability-name}` (e.g., `swag-admin-users-list-admins`)
- **App**: `{app-name}-{capability-name}` (e.g., `my-erp-sync-orders`)

The `McpToolCompilerPass` enforces unique names and throws on conflicts. The `shopware-` prefix is reserved for core tools; `AppMcpToolLoader` skips app tools whose computed name starts with `shopware-`.

## Folder structure
- `Authentication/` -- MCP authentication listener
- `Context/` -- Context bridging (McpContextProvider)
- `Controller/` -- HTTP endpoint for MCP protocol
- `Tool/` -- Individual MCP tool implementations
- `Prompt/` -- System prompts for AI context
- `Resource/` -- Static MCP resources
- `Command/` -- CLI commands (`debug:mcp`)
- `Loader/` -- Extension loaders for app capabilities (`AppMcpToolLoader`, `AppMcpPromptLoader`, `AppMcpResourceLoader`, `AppMcpCapabilityExecutor`, `AbstractAppMcpLoader`)
- `docs/` -- Documentation: tool reference, examples, security, setup, extensibility, user stories

## Feature flag

`Feature::isActive('MCP_SERVER')` is a **runtime** env-var check, not a compile-time gate. `FeatureFlagCompilerPass` removes services tagged `shopware.feature: { flag: MCP_SERVER }`, but MCP services are NOT tagged that way — they use `nullOnInvalid()` on their injected `mcp.*` dependencies instead.

`nullOnInvalid()` injects `null` only when the service does not exist in the container at all (i.e., `symfony/mcp-bundle` absent). Because the bundle is in `require` and registered unconditionally in `config/bundles.php`, MCP services are always present and `nullOnInvalid()` never resolves to `null`.

**Consequence:** `Feature::isActive('MCP_SERVER')` is the only meaningful runtime gate. The `=== null` null-checks in controllers/commands are a safety net for "bundle truly absent", not a feature-flag substitute.

**Do not remove `Feature::isActive('MCP_SERVER')` guards** in isolation. Full unflagging requires a single sweep: `feature.yaml` default, the PHP backend guards, and the Admin UI guard at `sw-integration-list.html.twig:214`.

## Conventions
- All classes use `@experimental stableVersion:v6.8.0 feature:MCP_SERVER` annotation
- All classes use `#[Package('framework')]` attribute
- Tools return JSON strings; the MCP protocol handles transport encoding
- Write tools default to `dryRun=true` for safety. Dry-run adds `SKIP_TRIGGER_FLOW` to the context to prevent Flow Builder actions during preview
- Entity tools validate entity existence with `registry->has()` before ACL checks to provide clear error messages
- Service IDs use FQCN; tags include `mcp.tool` for SDK discovery
- Tools declare prerequisites with `#[McpToolDependsOn('other-tool-name')]` (repeatable) — the allowlist UI auto-expands these when a user enables a tool; `debug:mcp` shows them in the Dependencies column
- Tools declare required ACL privileges with `#[McpToolRequires]` (repeatable) — **declarative only**; runtime enforcement still depends on `requirePrivilege()` calls and DAL ACL checks. The attribute is used by `debug:mcp` (Privileges column), the API (`/_action/mcp/tools`), and the Admin UI to help operators configure roles correctly

## Validating capabilities are loaded

How many layers you need to worry about depends on where the tool lives:

### Plugin tools (tagged `shopware.mcp.tool`)
Only one layer is required: **the DI tag**. The `McpToolCompilerPass` reads the `#[McpTool]` attribute via reflection and calls `addTool()` on the MCP server builder at compile time. Plugin lifecycle is respected: if the plugin is inactive the service is absent from the container and the tool is not registered.

### Core / in-tree bundle tools (tagged `mcp.tool` directly)
Two layers are required: the DI tag **and** the directory must appear in `mcp.yaml` `scan_dirs`. The MCP SDK's `DiscoveryLoader` scans those directories at runtime to find `#[McpTool]` attributes. Missing either causes the tool to be silently absent.

### Verification methods

| Method | What it covers | When to use |
|---|---|---|
| `bin/console debug:mcp` | Full registry — same source as the HTTP endpoint | Quick manual check during development |
| `McpCapabilityDiscoveryTest` | HTTP → `tools/list` (full kernel) | CI — authoritative end-to-end check |
| `McpServiceConfigTest` / `McpFeatureFlagTest` | DI layer only | Fast unit-level guard for tag/registration |

`bin/console debug:mcp` now uses the same `Registry` as the HTTP endpoint (populated by calling `Builder::build()`), so it shows core tools, plugin tools, and app tools in one view. It is the fastest way to check that a newly registered capability is visible.

**`McpCapabilityDiscoveryTest`** (`tests/integration/Core/Framework/Mcp/McpCapabilityDiscoveryTest.php`) boots the full kernel, authenticates, and calls the live MCP HTTP endpoint. It is the authoritative check that mirrors what the MCP Inspector does interactively. Add new capability names to its `expectedTools()` / `expectedPrompts()` / `expectedResources()` lists when adding new core capabilities.

## Extensibility
- **Plugins**: Tag services with `shopware.mcp.tool` -- the `McpToolCompilerPass` re-tags them as `mcp.tool` AND calls `addTool()` on the MCP server builder so they appear in both `debug:mcp` and the HTTP endpoint. No `scan_dirs` entry is needed. Use `McpToolResponse` for consistent error handling and response formatting.
- **Third-party Symfony bundles**: Same `shopware.mcp.tool` tag mechanism as plugins -- `McpToolCompilerPass` handles discovery. Gate the service file in the bundle's `build()` method with `Feature::has('MCP_SERVER')`. See `custom/bundles/SwagMcpExampleBundle/` for a worked example.
- **Apps**: Declare capabilities in `Resources/mcp.xml` -- parsed by `Mcp::createFromXmlFile()` (XXE-safe via `XmlUtils::loadFile()`), persisted by the respective Persister (`McpToolPersister`, `McpPromptPersister`, `McpResourcePersister`), loaded at runtime by the corresponding Loader (`AppMcpToolLoader`, `AppMcpPromptLoader`, `AppMcpResourceLoader`). App tool webhook payloads include `shopId` and `appVersion` in the `source` object. **App tools also support internal dispatch via `/api/script/{path}` -- see the Serverless app tools section below.**
- **In-tree Shopware bundles** (Storefront, etc.): Tag with **`mcp.tool`** directly (not `shopware.mcp.tool`) and ensure the bundle directory is listed in `mcp.yaml` `scan_dirs`. Using `shopware.mcp.tool` here would cause double-registration (compiler pass + scan_dirs).
- **Reserved prefix**: The `shopware-` prefix is reserved for core tools. App tools with names starting with `shopware-` are skipped during loading.

## Serverless app tools (app scripts)

App MCP tools can use `/api/script/{path}` as their `url` — Shopware dispatches these as internal subrequests, removing the need for an external server or `<setup>` registration handshake.

### How it works

1. `AppMcpCapabilityExecutor` detects URLs starting with `/` and dispatches a Symfony subrequest instead of a Guzzle HTTP call.
2. Arguments are passed as a `POST` form parameter named `arguments` (not JSON body), so Twig scripts can access them via `hook.request.request.all('arguments')`.
3. Auth headers from the parent MCP request are inherited — the subrequest runs in the integration's authenticated context, so DAL ACL is enforced normally.
4. `AppMcpToolLoader` SQL includes apps without a secret when their tool URL starts with `/`.

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

`McpToolPersister` validates at install/update time that every declared privilege appears in the app's manifest `<permissions>`. An app that declares `product:read` as a required privilege but does not have `<read>product</read>` in `<permissions>` will fail to install with a clear error message.

## Future ideas / backlog

### ACL / visibility
- **Filter `shopware://entities` resource by ACL** — `EntityListResource` currently returns all registered entities regardless of the caller's permissions. It should inject `McpContextProvider` and filter by `$context->isAllowed($entity . ':read')`, with a null-safe fallback for CLI/system contexts (return full list when there is no HTTP request).
- **`debug:mcp` entity visibility** — when `--integration SWIA...` is passed, add an "Entities" count column to the tools table (how many entities that integration can read for entity-tools). In the detail view (`debug:mcp shopware-entity-read --integration ...`), show the full sorted list of accessible entity names.

### Store API / shopper-side MCP
The current MCP server is admin-API only (`/api/_mcp`, integration key auth). There is no MCP endpoint for the Store API.

A store-API MCP would authenticate as a **customer** and expose the buyer journey (browse, cart, checkout, account). This is a fundamentally different security model from the admin MCP (operator/developer automation). The current `merchant-cart-*` / `merchant-storefront-search` tools from the merchant assistant app are a pragmatic middle ground — they run under admin auth but proxy to Store API internally on behalf of a customer context.

Open questions before implementing:
- Auth: customer session token, guest token, or a new MCP-specific customer credential?
- Scope: read-only browse vs. full cart/checkout mutations?
- Overlap: when does the admin MCP + store-API proxy (current approach) become insufficient vs. needing a real customer-scoped MCP?

### SDK-ready features (no upstream changes needed)
The symfony-mcp-bundle (v0.8.0) and mcp/sdk (v0.4.0) already implement the following — Shopware just needs to wire them up:

- **`listChanged` notifications** — SDK has `ToolListChangedNotification`, `ResourceListChangedNotification`, `PromptListChangedNotification` in `vendor/mcp/sdk/src/Schema/Notification/`. Call `$protocol->sendNotification()` from an event listener when capabilities change (e.g. after app install/uninstall). Lets AI clients refresh their tool list without reconnecting.
- **Resource subscriptions** — SDK has `ResourceSubscribeHandler` and `ResourceUnsubscribeHandler` (`vendor/mcp/sdk`). Resource templates (`#[McpResourceTemplate]`) are already wired up in core — see `ToolResultResource` and `Resource/AGENTS.md`. Subscriptions remain to be wired up if clients need push notifications when resources change.
- **Protocol-level pagination** — `RegistryInterface::getTools(?int $limit, ?string $cursor)` etc. already support cursor-based pagination; bundle has a `mcp.pagination_limit` config param. Shopware doesn't configure or expose it — relevant once tool/resource counts grow large.
- **Completion utility** — SDK has `CompletionCompleteHandler` + `CompletionProvider` interface with built-in `EnumCompletionProvider` / `ListCompletionProvider` (`vendor/mcp/sdk/src/Capability/Attribute/CompletionProvider.php`). Register providers on tool/prompt arguments to power autocomplete in MCP clients (e.g. entity name suggestions for the `entity` parameter on entity tools).

---

## Security

Every MCP request passes through three layers in order — see `docs/security.md` for the full reference including error messages and troubleshooting.

1. **Authentication** — `sw-access-key` + `sw-secret-access-key` headers required on every request
2. **Per-integration capability allowlist** — each integration stores a `mcp_allowlist` JSON object with `tools`, `resources`, and `prompts` keys (null per key = unrestricted; empty array = deny all). Configured via Settings → Integrations → Edit MCP Allowlist. `tools/list`, `resources/list`, and `prompts/list` responses are filtered; `tools/call`, `resources/read`, and `prompts/get` are rejected early with a clear error. Tool allowlist auto-expands transitive `#[McpToolDependsOn]` dependencies. **The `admin` flag does NOT bypass this layer** — it only bypasses layer 3 (ACL). **Scope**: enforced only for integration-authenticated requests (`sw-access-key` + `sw-secret-access-key`, or OAuth `client_credentials` for an integration key). Admin user bearer tokens issued via password/refresh grant (`client_id = administration`) resolve to no integration row in `McpAllowlistProvider::forAccessKey()` and fall back to unrestricted — the allowlist is effectively skipped for them.
3. **ACL / Privileges** — tools call `requirePrivilege()` before touching data. Missing privileges return `{"success": false, "error": "Missing privilege: ..."}`. Tools may also annotate their static requirements with `#[McpToolRequires]` so operators can configure roles correctly upfront — but this is informational only and does not replace the `requirePrivilege()` check.

Additional safeguards:
- **Audit logging**: tool invocations logged via `mcp` Monolog channel
- **App HMAC**: app tool calls signed with `RequestSigner` using the app secret
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

**App deactivation**: when an app is deactivated via `AppStateService::deactivateApp()`, its integration is soft-deleted (`deletedAt` set). This suspends MCP authentication for that integration — the DAL excludes soft-deleted rows, so token requests fail. Reactivating the app (`activateApp()`) clears `deletedAt` and restores access.
