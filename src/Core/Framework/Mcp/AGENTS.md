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
- **Examples**: `shopware-entity-search`, `shopware-entity-upsert`, `shopware-console-command`
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
- **Tools**: Single-responsibility PHP classes with `#[McpTool]` attributes, registered via XML service definitions
- **Feature flag**: All services tagged with `shopware.feature` flag `MCP_SERVER` -- removed from the container when disabled

## Naming convention
All capability names use hyphen-separated prefixes (`a-zA-Z0-9_-` only, no dots):
- **Core**: `shopware-{name}` (e.g., `shopware-entity-search`, `shopware-console-command`)
- **Plugin**: `{plugin-name}-{capability-name}` (e.g., `swag-admin-users-list-admins`)
- **App**: `{app-name}-{capability-name}` (e.g., `my-erp-sync-orders`)

The `McpToolCompilerPass` enforces unique names and throws on conflicts.

## Folder structure
- `Context/` -- Authentication and context bridging
- `Controller/` -- HTTP endpoint for MCP protocol
- `Tool/` -- Individual MCP tool implementations
- `Prompt/` -- System prompts for AI context
- `Resource/` -- Static MCP resources
- `Command/` -- CLI commands (`debug:mcp`)
- `Loader/` -- Extension loaders for app tools (`AppMcpToolLoader`, `AppMcpToolExecutor`)

## Conventions
- All classes use `@experimental stableVersion:v6.8.0 feature:MCP_SERVER` annotation
- All classes use `#[Package('framework')]` attribute
- Tools return JSON strings; the MCP protocol handles transport encoding
- Write tools default to `dryRun=true` for safety
- Service IDs use FQCN; tags include both `mcp.tool` (for SDK discovery) and `shopware.feature` (for flag gating)

## Extensibility
- **Plugins**: Tag services with `shopware.mcp.tool` -- the `McpToolCompilerPass` maps them to `mcp.tool`
- **Apps**: Declare tools in `Resources/mcp.xml` -- parsed by `Mcp::createFromXmlFile()`, persisted by `McpToolPersister`, loaded at runtime by `AppMcpToolLoader`
- **Console commands**: Plugins can expose commands to the `shopware-console-command` tool by tagging with `shopware.mcp.allowed_command`

## Security
- **Tool allowlist**: Configure `shopware.mcp.allowed_tools` to restrict exposed tools (empty = all allowed)
- **Console allowlist**: Configure `shopware.mcp.allowed_console_commands` for safe commands
- **Audit logging**: Tool invocations logged via `mcp` Monolog channel
- **App HMAC**: App tool calls signed with `RequestSigner` using app secret
