# MCP Commands

## Purpose
CLI commands for inspecting and debugging the MCP server.

## Prerequisites
The `MCP_SERVER` feature flag must be enabled. Add `MCP_SERVER=1` to your `.env` file.

## Available commands
- `debug:mcp` -- Lists all registered MCP tools, prompts, and resources with their descriptions and source

## Usage
```bash
bin/console debug:mcp                             # list all tools, prompts, and resources
bin/console debug:mcp shopware-entity-search      # full detail for one tool by name
bin/console debug:mcp shopware-context            # full detail for one prompt
bin/console debug:mcp shopware://entities         # full detail for one resource (name or URI)
bin/console debug:mcp --tools                     # list only tools
bin/console debug:mcp --prompts                   # list only prompts
bin/console debug:mcp --resources                 # list only resources
bin/console debug:mcp --integration=SWIA...       # restrict the tool list to what the given integration access key is allowed to see (applies the same allowlist the HTTP endpoint uses)
```

## What `debug:mcp` shows

The command uses the same `Registry` as the live MCP HTTP endpoint. It calls `Builder::build()` which runs all loaders and populates the registry identically to what `/api/_mcp` would serve. This means:

- **Core tools**: discovered via `scan_dirs` in `mcp.yaml`
- **Plugin tools**: registered via `shopware.mcp.tool` DI tag + `McpToolCompilerPass`
- **App tools**: loaded from the database by `AppMcpToolLoader` (requires DB connectivity)

The list view shows five columns: **Name**, **Description**, **Dependencies**, **Privileges**, and **Source**.

- `Source` shows the PHP class name for core/plugin tools and `(app-provided)` for app webhook tools.
- `Dependencies` shows tools declared via `#[McpToolDependsOn]` (comma-separated). Empty means no declared dependencies.
- `Privileges` shows ACL privileges declared via `#[McpToolRequires]` (comma-separated). Static privileges appear as-is (e.g. `system_config:read`); dynamic entity privileges appear as `<entity>:read`. Empty means no declared privileges (informational only — runtime enforcement via `requirePrivilege()` still applies).

If a tool appears in `debug:mcp`, it will also appear in `tools/list` — and vice versa.

## Setting up an MCP client
Use the built-in `integration:create` command to create credentials, then configure your MCP client manually. See `docs/setup.md` for details.
