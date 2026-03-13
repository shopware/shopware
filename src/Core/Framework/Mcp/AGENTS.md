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
- **Examples**: `shopware-entity-search`, `shopware-entity-upsert`, `shopware-product-create`
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

The `McpToolCompilerPass` enforces unique names and throws on conflicts.

## Folder structure
- `Authentication/` -- MCP authentication listener
- `Context/` -- Context bridging (McpContextProvider)
- `Controller/` -- HTTP endpoint for MCP protocol
- `Tool/` -- Individual MCP tool implementations
- `Prompt/` -- System prompts for AI context
- `Resource/` -- Static MCP resources
- `Command/` -- CLI commands (`debug:mcp`)
- `Loader/` -- Extension loaders for app capabilities (`AppMcpToolLoader`, `AppMcpPromptLoader`, `AppMcpResourceLoader`, `AppMcpToolExecutor`)
- `docs/` -- Documentation: tool reference, examples, security, setup, extensibility, user stories

## Conventions
- All classes use `@experimental stableVersion:v6.8.0 feature:MCP_SERVER` annotation
- All classes use `#[Package('framework')]` attribute
- Tools return JSON strings; the MCP protocol handles transport encoding
- Write tools default to `dryRun=true` for safety
- Service IDs use FQCN; tags include both `mcp.tool` (for SDK discovery) and `shopware.feature` (for flag gating)

## Validating capabilities are loaded

Getting a capability to appear in a client (Cursor, Claude Desktop) requires two independent layers to work:

1. **DI layer** -- service must be registered and tagged `mcp.tool` / `mcp.prompt` / `mcp.resource`
2. **SDK discovery layer** -- the tool class directory must be listed in `mcp.yaml` `scan_dirs` so the MCP SDK's attribute scanner can find the `#[McpTool]` attribute

Failure in either layer causes the capability to be silently absent. These are three ways to check:

| Method | What it covers | When to use |
|---|---|---|
| `bin/console debug:mcp` | Both layers (reads from live server) | Quick manual check during development |
| `McpCapabilityDiscoveryTest` | Both layers (HTTP → `tools/list`) | CI — catches regressions automatically |
| `McpServiceConfigTest` / `McpFeatureFlagTest` | DI layer only | Fast unit-level guard for tag/registration issues |

**`McpCapabilityDiscoveryTest`** (`tests/integration/Core/Framework/Mcp/McpCapabilityDiscoveryTest.php`) boots the full kernel, authenticates, and calls the live MCP HTTP endpoint. It is the authoritative check that mirrors what the MCP Inspector does interactively. Add new capability names to its `expectedTools()` / `expectedPrompts()` / `expectedResources()` lists when adding new capabilities.

## Extensibility
- **Plugins**: Tag services with `shopware.mcp.tool` -- the `McpToolCompilerPass` maps them to `mcp.tool` so they appear in the registry
- **Apps**: Declare capabilities in `Resources/mcp.xml` -- parsed by `Mcp::createFromXmlFile()`, persisted by the respective Persister (`McpToolPersister`, `McpPromptPersister`, `McpResourcePersister`), loaded at runtime by the corresponding Loader (`AppMcpToolLoader`, `AppMcpPromptLoader`, `AppMcpResourceLoader`)
- **Non-Core bundles**: MCP tools that depend on bundle-specific services live in that bundle (e.g., `src/Storefront/Mcp/Tool/` for Storefront-dependent tools). Tag them with **`mcp.tool`** directly — `McpToolCompilerPass` only remaps the `shopware.mcp.tool` tag used by plugins, not the internal `mcp.tool` tag. Using the wrong tag means the tool silently disappears from the registry.

## Security
- **Tool allowlist**: Configure `shopware.mcp.allowed_tools` to restrict exposed tools (empty = all allowed)
- **Audit logging**: Tool invocations logged via `mcp` Monolog channel
- **App HMAC**: App tool calls signed with `RequestSigner` using app secret
