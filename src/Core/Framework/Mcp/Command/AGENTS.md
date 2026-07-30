# MCP Commands

## Purpose
CLI commands for inspecting and debugging the MCP server.

## Available commands
- `debug:mcp` -- Lists all registered MCP tools, prompts, and resources with their descriptions and source

## Usage
```bash
bin/console debug:mcp                             # list all tools, prompts, and resources of both MCP servers
bin/console debug:mcp shopware-entity-search      # full detail for one tool by name
bin/console debug:mcp shopware-context            # full detail for one prompt
bin/console debug:mcp shopware://entities         # full detail for one resource (name or URI)
bin/console debug:mcp --tools                     # list only tools
bin/console debug:mcp --prompts                   # list only prompts
bin/console debug:mcp --resources                 # list only resources
bin/console debug:mcp --scope=api                 # only the Admin server (/api/_mcp)
bin/console debug:mcp --scope=store-api           # only the Store API server (/store-api/_mcp)
bin/console debug:mcp --integration=SWIA...       # restrict the tool list to what the given integration access key is allowed to see (applies the same allowlist the HTTP endpoint uses)
```

## Scopes

Shopware runs two independent MCP servers, each with its own builder and registry: the Admin server (`mcp.server.builder` / `mcp.registry`, tag `mcp.tool`) and the Store API server (`mcp.store_api.server.builder` / `mcp.store_api.registry`, tag `shopware.store_api_mcp.tool`). `debug:mcp` builds both and prints one block per server, so a tool registered on either endpoint is visible without knowing which one it belongs to.

Every section heading is prefixed with the server it belongs to (`Store API: Tools (17)`, `Admin API: Prompts (4)`). The block title alone is not enough: with a few dozen tools it scrolls out of view long before the reader reaches the prompts or resources of that same server. Keep the prefix when adding sections.

`--scope` takes the route scope ID of the endpoint (`ApiRouteScope::ID` = `api`, `StoreApiRouteScope::ID` = `store-api`), matching the `_routeScope` the two controllers declare. Omitting it inspects both. A capability lookup by name searches the selected servers in order and the detail view shows a **Scope** row naming the owning server.

`--integration` filters the Admin server only: integration allowlists are resolved from the `sw-access-key` of an Admin integration and are not evaluated for Store API requests. When both servers are listed, the command prints a note saying so instead of silently applying the filter to one half of the output.

## What `debug:mcp` shows

The command uses the same `Registry` instances as the live MCP HTTP endpoints. It calls `Builder::build()` per scope, which runs all loaders and populates each registry identically to what `/api/_mcp` and `/store-api/_mcp` would serve. This means:

- **Core tools**: discovered via `scan_dirs` in `mcp.yaml`
- **Plugin tools**: registered via `shopware.mcp.tool` DI tag + `McpToolCompilerPass`
- **App tools**: loaded from the database by `AppMcpToolLoader` (requires DB connectivity)
- **Store API tools**: registered via the `shopware.store_api_mcp.tool` DI tag + `StoreApiMcpServerBuilderCompilerPass`, listed under the Store API scope

The list view shows five columns: **Name**, **Group**, **Source**, **Dependencies**, and **Privileges**.

- `Group` shows the toolset a tool belongs to, resolved from `#[McpToolGroup]`, the owning app, or a shared name prefix.
- `Source` shows the PHP class name for core/plugin tools and `(app-provided)` for app webhook tools.
- `Dependencies` shows tools declared via `#[McpToolDependsOn]` (comma-separated). Empty means no declared dependencies.
- `Privileges` shows ACL privileges declared via `#[McpToolRequires]` (comma-separated). Static privileges appear as-is (e.g. `system_config:read`); dynamic entity privileges appear as `<entity>:read`. Empty means no declared privileges (informational only — runtime enforcement via `requirePrivilege()` still applies).

If a tool appears under a scope in `debug:mcp`, it is in that endpoint's registry and reachable via `tools/call` there. Whether it is advertised in a bare `tools/list` is a separate question, decided by progressive disclosure: only the `discovery` group is advertised until a client enables the tool's toolset.

## Setting up an MCP client
Use the built-in `integration:create` command to create credentials, then configure your MCP client manually. See `docs/setup.md` for details.
